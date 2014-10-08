<?php

namespace vesna\cart;

use yii\base\Component;
use yii\base\Event;
use Yii;


/**
 * Class Cart
 * @property CartPositionInterface[] $positions
 * @property int $count Total count of positions in the cart
 * @property int $cost Total cost of positions in the cart
 * @property bool $isEmpty Returns true if cart is empty
 * @property string $hash Returns hash (md5) of the current cart, that is uniq to the current combination
 * of positions, quantities and costs
 * @package \vesna\cart
 */
class ShoppingCart extends Component
{
    /** Triggered on position put */
    const EVENT_POSITION_PUT = 'putPosition';
    /** Triggered on position update */
    const EVENT_POSITION_UPDATE = 'updatePosition';
    /** Triggered on after position remove */
    const EVENT_BEFORE_POSITION_REMOVE = 'removePosition';
    /** Triggered on any cart change: add, update, delete position */
    const EVENT_CART_CHANGE = 'cartChange';
    
    /**
     * Shopping cart ID
     * @var string
     */
    public $cartId = __CLASS__;
    /**
     * @var array
     */

    /**
    *  Position model class name. Default:Product;
    *  @var string
    */
    public $positionClass = 'Product';

    public $discounts = [];
    /**
     * @var CartPositionInterface[]
     */
    protected $_positions = [];

    public function init()
    {
        $this->loadCart();
    }

    /**
     * @param CartPositionInterface $position
     * @param int $quantity
     */
    public function put($position, $quantity = 1)
    {
        if (isset($this->_positions[$position->getId()])) {
            $this->_positions[$position->getId()]->setQuantity(
                $this->_positions[$position->getId()]->getQuantity() + $quantity);
        } else {
            $position->setQuantity($quantity);
            $this->_positions[$position->getId()] = $position;
        }
        $this->trigger(self::EVENT_POSITION_PUT, new Event([
            'data' => $this->_positions[$position->getId()],
        ]));
        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'put', 'position' => $this->_positions[$position->getId()]],
        ]));
        $this->saveCart();
    }

    /**
     * @param CartPositionInterface $position
     * @param int $quantity
     */
    public function update($position, $quantity)
    {
        if ($quantity <= 0) {
            $this->remove($position);
            return;
        }

        if (isset($this->_positions[$position->getId()])) {
            $this->_positions[$position->getId()]->setQuantity($quantity);
        } else {
            $position->setQuantity($quantity);
            $this->_positions[$position->getId()] = $position;
        }
        $this->trigger(self::EVENT_POSITION_UPDATE, new Event([
            'data' => $this->_positions[$position->getId()],
        ]));
        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'update', 'position' => $this->_positions[$position->getId()]],
        ]));
        $this->saveCart();
    }

    /**
     * Removes position from the cart
     * @param CartPositionInterface $position
     */
    public function remove($position)
    {
        $this->trigger(self::EVENT_BEFORE_POSITION_REMOVE, new Event([
            'data' => $this->_positions[$position->getId()],
        ]));
        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'remove', 'position' => $this->_positions[$position->getId()]],
        ]));
        unset($this->_positions[$position->getId()]);
        $this->saveCart();
    }

    /**
     * Remove all positions
     */
    public function removeAll()
    {
        $this->_positions = [];
        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'removeAll'],
        ]));
        $this->saveCart();
    }

    /**
     * Returns position by it's id. Null is returned if position was not found
     * @param string $id
     * @return CartPositionInterface|null
     */
    public function getPositionById($id)
    {
        if ($this->hasPosition($id))
            return $this->_positions[$id];
        else
            return null;
    }

    /**
     * Checks whether cart position exists or not
     * @param string $id
     * @return bool
     */
    public function hasPosition($id)
    {
        return isset($this->_positions[$id]);
    }

    /**
     * @return CartPositionInterface[]
     */
    public function getPositions()
    {
        return $this->_positions;
    }

    /**
     * @param CartPositionInterface[] $positions
     */
    public function setPositions($positions)
    {
        $this->_positions = $positions;
        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'positions'],
        ]));
        $this->saveCart();
    }

    /**
     * Returns true if cart is empty
     * @return bool
     */
    public function getIsEmpty()
    {
        return count($this->_positions) == 0;
    }

    /**
     * @return int
     */
    public function getCount()
    {
        $count = 0;
        foreach ($this->_positions as $position)
            $count += $position->getQuantity();
        return $count;
    }

    /**
     * Return full cart cost as a sum of the individual positions costs
     * @param $withDiscount
     * @return int
     */
    public function getCost()
    {
        $cost = 0;
        foreach ($this->_positions as $position) {
            $cost += $position->getCost($withDiscount);
        }        
        return $cost;
    }

    /**
     * Returns hash (md5) of the current cart, that is unique to the current combination
     * of positions, quantities and costs. This helps us fast compare if two carts are the same, or not, also
     * we can detect if cart is changed (comparing hash to the one's saved somewhere)
     * @return string
     */
    public function getHash()
    {
        $data = [];
        foreach ($this->positions as $position) {
            $data[] = [$position->getId(), $position->getQuantity(), $position->getPrice()];
        }
        return md5(serialize($data));
    }

    protected function saveCart()
    {
        $cookies = Yii::$app->response->cookies;

        // if ($cookies->has('language')){

        // }else{                    
            $cookies->add(new \yii\web\Cookie([
                'name' => $this->cartId,
                'value' => json_encode($this->dumpCart()),
            ]);
        // }
        Yii::$app->session[$this->cartId] = serialize($this->_positions);
    }

    protected function loadCart()
    {
        if (isset(Yii::$app->session[$this->cartId])){
            $this->_positions = unserialize(Yii::$app->session[$this->cartId]);
        }else{
            $cookies = Yii::$app->response->cookies;
            if ($cookies->has($this->cartId)){
                $cart = json_decode($cookies[$this->cartId]->value,true);
                foreach ($cart as $id => $quantity) {
                    $model = $$this->positionClass::findOne($id);                    
                    $this->put($model,$quantity);
                }
            }
        }
    }

    protected function dumpCart(){
        $cart = [];
        foreach ($this->_positions as $key => $position) {
            $cart[$position->id] = $position->quantity;
        }
        return json_encode($cart);
    }
}
