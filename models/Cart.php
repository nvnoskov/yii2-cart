<?php

namespace vesna\cart\models;

use Yii;
use yii\behaviors\TimestampBehavior;
/**
 * This is the model class for table "cart".
 *
 * @property integer $id
 * @property string $code
 * @property string $data
 * @property string $contact
 * @property string $created_at
 * @property string $updated_at
 * @property integer $status
 */
class Cart extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'cart';
    }
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['data'], 'required'],
            [['data', 'contact'], 'string'],
            [['status','created_at','updated_at'], 'integer'],
            [['code'], 'string', 'max' => 255],
            [['code'], 'unique']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'code' => Yii::t('app', 'Code'),
            'data' => Yii::t('app', 'Cart info'),
            'contact' => Yii::t('app', 'Contact'),
            'status' => Yii::t('app', 'Status'),
        ];
    }

    public function getDataJSON($field='data') {
        if (is_string($this->$field)) {
            return (array) json_decode($this->$field, true);
        }
    }

    public function afterSave($insert, $changedAttributes){
        if(!$this->code){
            $this->code = $this->generateCode();
            $this->update();
        }
        return parent::afterSave($insert, $changedAttributes);
    }
    private function generateCode(){
        return mb_convert_case(base_convert(date('dmY').'0'.$this->id, 10, 35),MB_CASE_UPPER); 
    } 
}