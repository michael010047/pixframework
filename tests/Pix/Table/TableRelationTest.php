<?php

class Pix_Table_TableRelationTest_Table extends Pix_Table
{
    public function init()
    {
        $this->_name = 'table';
        $this->_primary = 't1_id';

        $this->_columns['t1_id'] = array('type' => 'int', 'auto_increment' => true, 'unsigned' => true);
        $this->_columns['value'] = array('type' => 'text', 'default' => 'default');

        $this->_relations['table2'] = array('rel' => 'has_one', 'type' => 'Pix_Table_TableRelationTest_Table2', 'delete' => true, 'foreign_key' => 't1_id');
        $this->_relations['table3s'] = array('rel' => 'has_many', 'type' => 'Pix_Table_TableRelationTest_Table3', 'delete' => true, 'foreign_key' => 't3_t1id');
    }
}

class Pix_Table_TableRelationTest_Table2 extends Pix_Table
{
    public function init()
    {
        $this->_name = 'table2';
        $this->_primary = 't2_id';

        $this->_columns['t2_id'] = array('type' => 'int');
        $this->_columns['value'] = array('type' => 'text', 'default' => 'default');
    }
}

class Pix_Table_TableRelationTest_Table3 extends Pix_Table
{
    public function __construct()
    {
        $this->_name = 'table3';
        $this->_primary = array('t3_id');

        $this->_columns['t3_id'] = array('type' => 'int', 'auto_increment' => true);
        $this->_columns['t3_t1id'] = array('type' => 'int');
        $this->_columns['value'] = array('type' => 'enum', 'list' => array('on', 'off'));
    }
}

class Pix_Table_TableRelationTest extends PHPUnit_Framework_TestCase
{
    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public function testCreateRelationHasOne()
    {
        $db = $this->getMock('Pix_Table_Db_Adapter_Abstract', array('insertOne'));
        Pix_Table_TableRelationTest_Table2::setDb($db);

        $db->expects($this->once())
            ->method('insertOne')
            ->with($this->isInstanceOf('Pix_Table_TableRelationTest_Table2'), array('t2_id' => 1002, 'value' => 't2_value'))
            ->will($this->returnValue(null));

        $row = new Pix_Table_Row(array(
            'tableClass' => 'Pix_Table_TableRelationTest_Table',
            'data' => array('t1_id' => 1002, 'value' => 'test_create_relation')
        ));
        $row2 = $row->create_table2(array('value' => 't2_value', 'no_this_column' => 'value'));
        $this->assertTrue(Pix_Table::is_a($row2, 'Pix_Table_TableRelationTest_Table2'));
        $this->assertEquals($row2->t2_id, $row->t1_id);
        $this->assertEquals($row2->value, 't2_value');

        // has_many
        $db = $this->getMock('Pix_Table_Db_Adapter_Abstract', array('insertOne'));
        Pix_Table_TableRelationTest_Table3::setDb($db);

        $db->expects($this->once())
            ->method('insertOne')
            ->with($this->isInstanceOf('Pix_Table_TableRelationTest_Table3', array('t3_t1id' => '1002', 'value' => 'on')))
            ->will($this->returnValue(5566));

        $row3 = $row->create_table3s(array('value' => 'on', 'no_this_column' => 'value'));
        $this->assertEquals($row3->t3_id, 5566);
        $this->assertEquals($row3->t3_t1id, 1002);
        $this->assertEquals($row3->value, 'on');

        // has_many empty
        $db = $this->getMock('Pix_Table_Db_Adapter_Abstract', array('insertOne'));
        Pix_Table_TableRelationTest_Table3::setDb($db);

        $db->expects($this->once())
            ->method('insertOne')
            ->with($this->isInstanceOf('Pix_Table_TableRelationTest_Table3'), array('t3_t1id' => '1002'))
            ->will($this->returnValue(5567));

        $row3 = $row->create_table3s();
        $this->assertEquals($row3->t3_id, 5567);
        $this->assertEquals($row3->t3_t1id, 1002);
        $this->assertEquals($row3->value, null);
    }

    /**
     * testDelete 測試 relation 的 delete = true 要可以 work
     * 
     * @access public
     * @return void
     */
    public function testDelete()
    {
        $db = $this->getMock('Pix_Table_Db_Adapter_Abstract', array('deleteOne', 'fetchOne', 'fetch'));

        $row = new Pix_Table_Row(array(
            'tableClass' => 'Pix_Table_TableRelationTest_Table',
            'data' => array('t1_id' => 1000, 'value' => 'delete_me')
        ));

        $db->expects($this->once())
            ->method('fetchOne')
            ->with($this->isInstanceOf('Pix_Table_TableRelationTest_Table2'), array(1000))
            ->will($this->returnValue(array('t2_id' => 1000, 'value' => 'foo')));

        $search = Pix_Table_Search::factory(array('t3_t1id' => 1000), Pix_Table_TableRelationTest_Table3::getTable());
        $db->expects($this->once())
            ->method('fetch')
            ->with($this->isInstanceOf('Pix_Table_TableRelationTest_Table3'), $search, '*')
            ->will($this->returnValue(array(array('t3_id' => 9999, 't3_t1id' => 1000, 'value' => 'bar'))));

        $db->expects($this->exactly(3))
            ->method('deleteOne')
            ;

        Pix_Table_TableRelationTest_Table::setDb($db);
        Pix_Table_TableRelationTest_Table2::setDb($db);
        Pix_Table_TableRelationTest_Table3::setDb($db);

        $row->delete();
    }

    public function testRelation()
    {
        $row = new Pix_Table_Row(array(
            'tableClass' => 'Pix_Table_TableRelationTest_Table',
            'data' => array('t1_id' => 1001, 'value' => 'delete_me')
        ));

        $db = $this->getMock('Pix_Table_Db_Adapter_Abstract', array('fetchOne'));

        $db->expects($this->once())
            ->method('fetchOne')
            ->with($this->isInstanceOf('Pix_Table_TableRelationTest_Table2'), array(1001))
            ->will($this->returnValue(null));

        Pix_Table_TableRelationTest_Table2::setDb($db);

        $this->assertEquals($row->table2, null);
    }
}
