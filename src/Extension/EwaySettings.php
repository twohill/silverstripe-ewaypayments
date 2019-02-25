<?php

namespace Twohill\Eway\Extension;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataExtension;

class EwaySettings extends DataExtension
{
    private static $db = [
        'EWay_CustomerID' => 'Varchar(255)',
        'EWay_UserName' => 'Varchar(255)',
        'EWay_CompanyName' => 'Varchar(255)',
        'EWay_PageTitle' => 'Varchar(50)',
        'EWay_PageDescription' => 'Varchar(255)',
        'EWay_PageFooter' => 'Varchar(255)',
        'EWay_Language' => 'Varchar(2)',
        'EWay_Currency' => 'Varchar(5)',
        'EWay_CustomersCanModifyDetails' => 'Boolean',
        'EWay_ServerRequestURL' => 'Varchar(255)',
        'EWay_ServerResultURL' => 'Varchar(255)',
        'EWay_ReturnAction' => 'Varchar(50)',
        'EWay_CancelAction' => 'Varchar(50)',
    ];

    private static $has_one = [
        'EWay_CompanyLogo' => Image::class, // Note this will only work if hosted via https
        'EWay_PageBanner' => Image::class,   // Same, and is resized to 960x65
        'EWay_ReturnPage' => SiteTree::class,
        'EWay_CancelPage' => SiteTree::class,
    ];

    private static $defaults = [
        'EWay_CustomerID' => '87654321',
        'EWay_UserName' => 'TestAccount',
        'EWay_ServerRequestURL' => 'https://nz.ewaygateway.com/Request/',
        'EWay_ServerResultURL' => 'https://nz.ewaygateway.com/Result/',
        'EWay_Language' => 'EN',
        'EWay_Currency' => 'NZD'
    ];

    public function updateFieldLabels(&$labels)
    {
        $labels = array_merge($labels, [
            'EWay_CustomerID' => 'Customer ID',
            'EWay_UserName' => 'User Name',
            'EWay_CompanyName' => 'Company Name',
            'EWay_Currency' => 'Currency',
            'EWay_PageTitle' => 'The title text of the payment page',
            'EWay_PageDescription' => 'Used as a greeting message to the customer and is displayed above order details',
            'EWay_PageFooter' => 'Displayed below customer details. Useful for contact details',
            'EWay_CustomersCanModifyDetails' => 'Customers are allowed to use different contact details than what they registered to the site with',
            'EWay_ServerRequestURL' => 'Request URL. NZ Default: https://nz.ewaygateway.com/Request/',
            'EWay_ServerResultURL' => 'Result URL. NZ Default: https://nz.ewaygateway.com/Result/',
            'EWay_ReturnAction' => 'An optional action to run on the return page',
            'EWay_CancelAction' => 'An optional action to run on the cancel page',
        ]);
    }

    public function updateCMSFields(FieldList $fields)
    {
        $payments = $this->owner->scaffoldFormFields(['restrictFields' => array_keys(self::$db)]);

        $payments->removeByName('EWay_Language');
        $payments->insertBefore('Currency',
            DropdownField::create('EWay_Language', 'Language', [
                'EN' => 'English',
                'FR' => 'French',
                'DE' => 'German',
                'ES' => 'Spanish',
                'NL' => 'Dutch'
            ])
        );

        $payments->insertBefore('EWay_ReturnAction',
            TreeDropdownField::create("EWay_ReturnPageID",
                "Choose a page to go to when returning from the payments page",
                "Page")

        );

        $payments->insertBefore('EWay_CancelAction',
            TreeDropdownField::create("EWay_CancelPageID",
                "Choose a page to go to when cancelling from the payments page",
                "Page")
        );


        $payments->push(UploadField::create('EWay_CompanyLogo',
            'Image to display on secure payment page. WARNING: Only works if site accessible over HTTPS'));
        $payments->push(UploadField::create('EWay_PageBanner',
            'Banner image to display on secure payment page. WARNING: Only works if site accessible over HTTPS. Will be resized to 960px x 65px'));

        $fields->addFieldsToTab('Root.EWayPayments', $payments->dataFields());
    }
}
