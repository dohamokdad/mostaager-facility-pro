<?php

class Mostaager_DB_Wrapper_Test extends WP_UnitTestCase
{
    public function test_db_wrapper_instantiates()
    {
        $db = Mostaager_DB::get_instance();
        $this->assertInstanceOf(Mostaager_DB::class, $db);
    }

    public function test_db_wrapper_returns_array_for_facilities()
    {
        $db = Mostaager_DB::get_instance();
        $this->assertIsArray($db->get_facilities_by_manager(get_current_user_id()));
    }
}
