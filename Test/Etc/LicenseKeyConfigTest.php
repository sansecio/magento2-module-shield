<?php

namespace Sansec\Shield\Test\Etc;

use PHPUnit\Framework\TestCase;
use Sansec\Shield\Model\Config;

class LicenseKeyConfigTest extends TestCase
{
    private function xpath(string $file): \DOMXPath
    {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->load(__DIR__ . '/../../etc/' . $file));
        return new \DOMXPath($dom);
    }

    public function testAdminFieldIsObscuredAndEncrypted()
    {
        $fields = $this->xpath('adminhtml/system.xml')
            ->query('//section[@id="sansec_shield"]/group[@id="general"]/field[@id="license_key"]');

        $this->assertSame(1, $fields->length);
        $field = $fields->item(0);
        $this->assertSame('obscure', $field->getAttribute('type'));
        $this->assertSame(
            'Magento\Config\Model\Config\Backend\Encrypted',
            trim($field->getElementsByTagName('backend_model')->item(0)->textContent)
        );
    }

    public function testLicenseKeyIsRegisteredAsSensitive()
    {
        $items = $this->xpath('di.xml')->query(
            '//type[@name="Magento\Config\Model\Config\TypePool"]'
            . '/arguments/argument[@name="sensitive"]/item[@name="' . Config::XML_PATH_LICENSE_KEY . '"]'
        );

        $this->assertSame(1, $items->length);
        $this->assertSame('1', trim($items->item(0)->textContent));
    }
}
