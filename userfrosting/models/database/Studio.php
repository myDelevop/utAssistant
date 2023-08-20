<?php
        
namespace UserFrosting;



class Studio extends UFModel {

    protected static $_table_id = "utAss_studio";


	public function users() {
		$link_table = Database::getSchemaTable('utAss_as_studio_user')->name;
		return $this->belongsToMany('UserFrosting\User', $link_table);
	}
    
	public function tasks() {
		$link_table = Database::getSchemaTable('utAss_studio')->name;
		return $this->hasMany('UserFrosting\Task')->getResults();
	}
	
	
}
