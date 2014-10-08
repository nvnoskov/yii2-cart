<?php

namespace vesna\cart\components;

use yii\base\Component;
use yii\base\Event;
use Yii;

use vesna\cart\models\Cart as CartModel;


/**
 * Class Cart
 * @property PositionTrait[] $positions
 * @property int $count Total count of positions in the cart
 * @property int $cost Total cost of positions in the cart
 * @property bool $isEmpty Returns true if cart is empty
 * @property string $hash Returns hash (md5) of the current cart, that is uniq to the current combination
 * of positions, quantities and costs
 * @package \vesna\cart
 */
class Cart extends Component
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
     * @var PositionTrait[]
     */
    protected $_positions = [];

    public function init()
    {
        $this->loadCart();
    }

    /**
     * @param PositionTrait $position
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
     * @param PositionTrait $position
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
     * @param PositionTrait $position
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
     * @return PositionTrait|null
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
     * @return PositionTrait[]
     */
    public function getPositions()
    {
        return $this->_positions;
    }

    /**
     * @param PositionTrait[] $positions
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
            $cost += $position->getCost();
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
    /**
    * Return cart code in DB
    * @return string
    */
    public function getCode()
    {
        $readCookies = Yii::$app->request->cookies;
        if (($cookie = $readCookies->get($this->cartId)) !== null) {
            $cart = (array)json_decode($cookie->value,true);                   
            $code = $cart['code'];
        }
        return $code;
    }

    public function updateInfo($info){
        $cartModel = CartModel::find()->where(['code'=>$this->code])->one();
        if($cartModel){
            $cartModel->contact = json_encode($info,JSON_UNESCAPED_UNICODE);
            $cartModel->update();
        }

    }

    protected function saveCart()
    {
        $readCookies = Yii::$app->request->cookies;
        if (($cookie = $readCookies->get($this->cartId)) === null) {
            $cartModel = new CartModel;
            $cartModel->data = $this->dumpCart();
            $cartModel->save();
            $code = $cartModel->code; 

        }else{            
            $cart = (array)json_decode($cookie->value,true);                   
            $code = $cart['code'];
            CartModel::updateAll([
                'data'=>$this->dumpCart(),'updated_at'=>time()
            ],'code=:code',[':code'=>$code]);
        }         
                         
        $cookies = Yii::$app->response->cookies;
        $cookies->add(new \yii\web\Cookie([
            'name' => $this->cartId,
            'value' => $this->dumpCart($code),
        ]));
        
        Yii::$app->session[$this->cartId] = serialize($this->_positions);
    }

    protected function loadCart()
    {
        if (isset(Yii::$app->session[$this->cartId])){
            $this->_positions = unserialize(Yii::$app->session[$this->cartId]);
        }else{
            $cookies = Yii::$app->request->cookies;            
            if (($cookie = $cookies->get($this->cartId)) !== null) {
                $cart = (array)json_decode($cookie->value,true);
                foreach ((array)$cart['data'] as $id => $quantity) {                    
                    $positionClass = new $this->positionClass;                    
                    if($model = $positionClass::findOne($id)){
                        $this->put($model,$quantity);
                    }
                }
            
            }
        }
    }

    protected function dumpCart($code=false){
        $cart = [];
        foreach ($this->_positions as $key => $position) {
            $cart[$position->id] = $position->quantity;
        }
        if($code){
            $cart = [
                'code'=>$code,
                'data'=>$cart
            ];
        }
        return json_encode($cart);
    }
}
