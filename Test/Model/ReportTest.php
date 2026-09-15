<?php

namespace Sansec\Shield\Test\Model;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\MessageQueue\PublisherInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface as Logger;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\IP;
use Sansec\Shield\Model\Report;
use Sansec\Shield\Model\Report\Consumer;
use Sansec\Shield\Model\Serializer;
use Sansec\Shield\Test\RequestStub;

class ReportTest extends TestCase
{
    /** @var array[] */
    private $posts = [];

    /** @var array[] */
    private $published = [];

    /** @var PublisherInterface */
    private $publisher;

    private function buildReport(bool $reportEnabled = true): Report
    {
        $config = $this->createMock(Config::class);
        $config->method('isReportEnabled')->willReturn($reportEnabled);
        $config->method('getLicenseKey')->willReturn('key');
        $config->method('getReportUrl')->willReturn('https://shield.example.com/report');

        $curl = $this->createMock(Curl::class);
        $curl->method('getStatus')->willReturn(200);
        $curl->method('post')->willReturnCallback(function ($url, $data) {
            $this->posts[] = [$url, $data];
        });

        $curlFactory = $this->getMockBuilder(CurlFactory::class)
            ->disableOriginalConstructor()
            ->disableAutoload()
            ->setMethods(['create'])
            ->getMock();
        $curlFactory->method('create')->willReturn($curl);

        $this->publisher = $this->createMock(PublisherInterface::class);
        $this->publisher->method('publish')->willReturnCallback(function ($topic, $data) {
            $this->published[] = [$topic, $data];
        });

        return new Report(
            $config,
            $curlFactory,
            $this->createMock(Logger::class),
            new Serializer(),
            new IP(),
            $this->createMock(ProductMetadataInterface::class),
            $this->publisher
        );
    }

    public function testDeferredReportIsNotPostedImmediately()
    {
        $report = $this->buildReport();
        $report->sendReportDeferred(new RequestStub(), []);

        $this->assertSame([], $this->posts);

        $report->flushDeferredReports();
        $this->assertCount(1, $this->posts);
    }

    public function testFlushPostsEveryDeferredReport()
    {
        $report = $this->buildReport();
        $report->sendReportDeferred(new RequestStub('', 'POST', '/first'), []);
        $report->sendReportDeferred(new RequestStub('', 'POST', '/second'), []);
        $report->flushDeferredReports();

        $this->assertCount(2, $this->posts);
        $this->assertSame('https://shield.example.com/report', $this->posts[0][0]);
        $this->assertStringContainsString('"uri":"\/first"', $this->posts[0][1]);
        $this->assertStringContainsString('"uri":"\/second"', $this->posts[1][1]);
        $this->assertStringContainsString('"type":"report"', $this->posts[0][1]);
    }

    public function testFlushIsIdempotent()
    {
        $report = $this->buildReport();
        $report->sendReportDeferred(new RequestStub(), []);
        $report->flushDeferredReports();
        $report->flushDeferredReports();

        $this->assertCount(1, $this->posts);
    }

    public function testNothingIsQueuedWhenReportingDisabled()
    {
        $report = $this->buildReport(false);
        $report->sendReportDeferred(new RequestStub(), []);
        $report->flushDeferredReports();

        $this->assertSame([], $this->posts);
    }

    public function testPublishReportSendsThePayloadToTheTopicWithoutPosting()
    {
        $report = $this->buildReport();
        $report->publishReport(new RequestStub('', 'POST', '/attack'), []);

        $this->assertSame([], $this->posts);
        $this->assertCount(1, $this->published);
        $this->assertSame(Report::TOPIC_REPORT, $this->published[0][0]);
        $this->assertStringContainsString('"uri":"\\/attack"', $this->published[0][1]);
        $this->assertStringContainsString('"type":"report"', $this->published[0][1]);
    }

    public function testNothingIsPublishedWhenReportingDisabled()
    {
        $report = $this->buildReport(false);
        $report->publishReport(new RequestStub(), []);

        $this->assertSame([], $this->published);
        $this->assertSame([], $this->posts);
    }

    public function testConsumerPostsThePayloadItReceives()
    {
        $report = $this->buildReport();
        (new Consumer($report))->process('{"type":"report"}');

        $this->assertCount(1, $this->posts);
        $this->assertSame('https://shield.example.com/report', $this->posts[0][0]);
        $this->assertSame('{"type":"report"}', $this->posts[0][1]);
        $this->assertSame([], $this->published);
    }
}
