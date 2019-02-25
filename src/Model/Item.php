<?php

namespace Twohill\Eway\Model;


use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBCurrency;

/**
 * Class Item
 * @package Twohill\Eway\Model
 *
 * @property string $Description
 * @property int $Items
 * @property double $CostPerItem
 * @property int $EwayPaymentID
 *
 * @method Payment EwayPayment
 */
class Item extends DataObject
{
    private static $table_name = 'EwayPayment_Item';

    private static $db = array(
        'Description' => 'Varchar(255)',
        'Items' => 'Int',
        'CostPerItem' => 'Currency',
    );

    private static $has_one = array(
        'EWayPayment' => Payment::class,
    );

    public function getTotalForLine() {
        $amount = new DBCurrency();
        $amount->setValue($this->CostPerItem * $this->Items);
        return $amount;
    }
}
