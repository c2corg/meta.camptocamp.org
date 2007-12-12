<?php
/*
 * Base class; DO NOT EDIT
 *
 * auto-generated by the sfDoctrine plugin
 */
class BaseRegion extends sfDoctrineRecord
{
  
  
  public function setTableDefinition()
  {
    $this->setTableName('regions');

    $this->hasColumn('name', 'string', 100, array (  'notnull' => true,));
    $this->hasColumn('external_region_id', 'integer', 4, array ());
    $this->hasColumn('system_id', 'string', 4, array (  'notnull' => true,));
  }
  

  
  public function setUp()
  {
    $this->hasMany('Region as Outing_Regions', array('refClass' => 'Outing_Region', 'local' => 'region_id', 'foreign' => 'region_id'));
  }
  
}
