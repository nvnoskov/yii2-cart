<?php

namespace vesna\cart;

use yii\base\Model;

/**
 * Trait PositionTrait
 * @property int $quantity Returns quantity of cart position
 * @property int $cost Returns cost of cart position. Default value is 'price * quantity'
 * @package vesna\cart
 */
trait PositionTrait
{
    protected $_quantity;

    /**
     * Set position discount sum
     * @param float $price
     * @return void
     */
    public function setDiscountPrice($price)
    {
        $this->discountPrice = $price;
    }

    public function getQuantity()
    {
        return $this->_quantity;
    }

    public function setQuantity($quantity)
    {
        $this->_quantity = $quantity;
    }

    /**
     * Default implementation for getCost function. Cost is calculated as price * quantity
     * @return int
     */
    public function getCost()
    {        
        $cost = $this->getQuantity() * $this->getPrice();        
        return $cost;
    }
} 