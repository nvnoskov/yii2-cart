<?php

use yii\db\Schema;
use yii\db\Migration;

class m141008_115039_cartModel extends Migration
{
    public function up()
    {
    	$this->createTable('{{%cart}}', [
            'id' => 'pk',
            'code' => Schema::TYPE_STRING . ' NOT NULL',            
            'data' => Schema::TYPE_TEXT,
            'contact' => Schema::TYPE_TEXT,
            'created_at' => 'integer',
            'updated_at' => 'integer',
            'status' => Schema::TYPE_BOOLEAN.' DEFAULT 0' ,
        ]);
        $this->createIndex('cart_code', '{{%cart}}','code',true);
        $this->createIndex('cart_status', '{{%cart}}','status');
    }

    public function down()
    {
        $this->dropTable('{{%cart}}');
    }
}
