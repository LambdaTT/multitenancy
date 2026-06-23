<?php

namespace Multitenancy\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreateTableTenant extends Migration
{
  public function apply()
  {
    $this->onDatabase('multitenancy')
      ->Table('MTN_TENANT', 'Tenant')
      ->id('id_snd_tenant') // int primary key auto increment
      ->string('ds_subdomain', 60)
      ->string('ds_customkey', 255)->nullable()->setDefaultValue(null)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime('dt_updated')->nullable()->setDefaultValue(null)
      ->string('ds_name', 100)
      ->string('ds_database_name', 100)
      ->string('ds_database_user', 100)
      ->string('ds_database_pass', 100)
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_subdomain')
      ->Index('CUSTOM_KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_customkey')
      ;
  }
}
