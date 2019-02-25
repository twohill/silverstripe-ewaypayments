<?php

namespace Twohill\Eway\Model;

use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;

/**
 * Class Payment
 * @package Twohill\Eway\Model
 *
 * @property string $FirstName
 * @property string $LastName
 * @property string $Address
 * @property string $City
 * @property string $State
 * @property string $PostCode
 * @property string $Country
 * @property string $Email
 * @property string $Phone
 * @property string $InvoiceDescription
 * @property string $Reference
 * @property double $Amount
 * @property string $Status
 * @property string $AuthCode
 * @property string $TransactionNumber
 * @property string $InvoiceReference
 *
 * @method HasManyList Items
 */
class Payment extends DataObject
{
    private static $table_name = 'EWayPayment';

    private static $db = [
        'FirstName' => 'Varchar(255)',
        'LastName' => 'Varchar(255)',
        'Address' => 'Varchar(255)',
        'City' => 'Varchar(255)',
        'State' => 'Varchar(50)',
        'PostCode' => 'Varchar(10)',
        'Country' => 'Varchar(50)',
        'Email' => 'Varchar(255)',
        'Phone' => 'Varchar(20)',
        'InvoiceDescription' => 'Varchar(255)',
        'Reference' => 'Varchar(255)',
        'Amount' => 'Currency',
        'Status' => 'Enum("New, Cancelled, Completed")',
        'AuthCode' => 'Varchar(20)',
        'TransactionNumber' => 'Varchar(20)',
        'InvoiceReference' => 'Varchar(20)',
    ];

    private static $has_many = [
        'Items' => Item::class,
    ];

    /**
     * Gets a read-only fieldlist of all the fields
     */
    public function getViewingFields()
    {
        $fields = new FieldList();

        foreach (self::$db as $fieldName => $fieldType) {
            $fields->push(
                new ReadOnlyField(self::baseClass() . $fieldName, $this->FieldLabel($fieldName),
                    $this->$fieldName)
            );
        }

        return $fields;
    }

    public function onBeforeDelete()
    {
        foreach ($this->Items() as $i) {
            $i->delete();
        }
        parent::onBeforeDelete();
    }

    public function getFrontEndFields($params = null)
    {
        $fields = parent::getFrontEndFields($params);
        $fields->removeByName('Email');
        $fields->insertBefore('Phone', EmailField::create('Email'));
        $fields->removeByName('Status');
        $fields->removeByName('Reference');
        $fields->removeByName('AuthCode');
        $fields->removeByName('TransactionNumber');
        $fields->makeFieldReadonly('InvoiceDescription');
        $fields->makeFieldReadonly('Amount');

        return $fields;
    }
}
