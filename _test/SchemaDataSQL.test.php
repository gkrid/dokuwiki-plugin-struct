<?php

namespace plugin\struct\test;

// we don't have the auto loader here
spl_autoload_register(array('action_plugin_struct_autoloader', 'autoloader'));

/**
 * Class SchemaData for testing
 *
 * Makes protected methods accessible and avoids database initialization
 *
 * @package plugin\struct\test
 */
class SchemaData extends \plugin\struct\meta\SchemaData {

    public function __construct($table, $page, $ts) {
        // we do intialization by parent here, because we don't need the whole database behind the class

        $this->page = $page;
        $this->table = $table;
        $this->ts = $ts;
    }

    public function buildGetDataSQL($singles, $multis) {
        return parent::buildGetDataSQL($singles, $multis);
    }

    public function consolidateData($DBdata, $labels) {
        return parent::consolidateData($DBdata, $labels);
    }

}

/**
 * Tests for the building of SQL-Queries for the struct plugin
 *
 * @group plugin_struct
 * @group plugins
 *
 */
class schemaDataSQL_struct_test extends \DokuWikiTest {

    protected $pluginsEnabled = array('struct',);

    /**
     * Testdata for @see schemaDataSQL_struct_test::test_buildGetDataSQL
     *
     * @return array
     */
    public static function buildGetDataSQL_testdata() {
        $schemadata = new SchemaData('testtable', 'pagename', 27);

        /** @noinspection SqlResolve */
        return array(
            array(
                array(
                    'obj' => $schemadata,
                    'singles' => array(1,2),
                    'multis' => array(),
                ),
                "SELECT col1,col2
                   FROM data_testtable DATA
                  WHERE DATA.pid = ?
                    AND DATA.rev = ?",
                array('pagename', 27),
                'no multis, with ts',
            ),
            array(
                array(
                    'obj' => $schemadata,
                    'singles' => array(1,2),
                    'multis' => array(3),
                ),
                "SELECT col1,col2,M3.value AS col3
                   FROM data_testtable DATA
                   LEFT OUTER JOIN multivals M3
                     ON DATA.pid = M3.pid
                    AND DATA.rev = M3.rev
                    AND M3.tbl = 'data_testtable'
                    AND M3.colref = 3
                  WHERE DATA.pid = ?
                    AND DATA.rev = ?",
                array('pagename', 27,),
                'one multi, with ts',
            ),
        );
    }

    /**
     * Turns subsequent whitespace into single ones
     *
     * Makes comparing sql statements a bit simpler as it ignores formatting
     *
     * @param $string
     * @return string
     */
    protected function cleanWS($string) {
        return preg_replace('/\s+/s', ' ', $string);
    }

    /**
     * @dataProvider buildGetDataSQL_testdata
     *
     * @covers       plugin\struct\meta\SchemaData::buildGetDataSQL
     *
     * @param string $expected_sql
     * @param string $msg
     *
     */
    public function test_buildGetDataSQL($testvals, $expected_sql, $expected_opt, $msg) {
        /** @var SchemaData $obj */
        $obj = $testvals['obj'];
        list($actual_sql, $actual_opt) = $obj->buildGetDataSQL(
            $testvals['singles'],
            $testvals['multis']
        );

        $this->assertSame($this->cleanWS($expected_sql), $this->cleanWS($actual_sql), $msg);
        $this->assertEquals($expected_opt, $actual_opt, $msg);
    }

    /**
     * Testdata for @see schemaDataSQL_struct_test::test_consolidateData
     *
     * @return array
     */
    public static function consolidateData_testdata() {
        return array(
            array(
                array(
                    array('col1' => 'value1', 'col2' => 'value2.1',),
                    array('col1' => 'value1', 'col2' => 'value2.2',),
                ),
                array('1' => 'columnname1', '2' => 'columnname2',),
                array('columnname1' => 'value1',
                    'columnname2' => array('value2.1', 'value2.2',),),
                '',
            ),
        );
    }

    /**
     * @dataProvider consolidateData_testdata
     *
     * @param array  $testdata
     * @param array  $testlabels
     * @param array  $expected_data
     * @param string $msg
     */
    public function test_consolidateData($testdata, $testlabels, $expected_data, $msg){

        // act
        $schemadata = new SchemaData('', '', null);
        $actual_data = $schemadata->consolidateData($testdata, $testlabels);

        // assert
        $this->assertEquals($expected_data, $actual_data, $msg);
    }
}
