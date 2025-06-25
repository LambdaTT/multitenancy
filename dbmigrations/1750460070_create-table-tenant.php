<?php

namespace Multitenancy\Migrations;

use SplitPHP\DbMigrations\Migration;
use SplitPHP\Database\DbVocab;

class CreateTableTenant extends Migration
{
  public function apply()
  {
    $this->onDatabase('multitenancy')
      ->Table('MTN_TENANT', 'Tenant')
      ->id('id_snd_tenant') // int primary key auto increment
      ->string('ds_key', 60)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime('dt_updated')->nullable()->setDefaultValue(null)
      ->string('ds_name', 100)
      ->string('ds_database_name', 100)
      ->string('ds_database_user_main', 100)
      ->string('ds_database_pass_main', 100)
      ->string('ds_database_user_readonly', 100)
      ->string('ds_database_pass_readonly', 100)
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key');
  }
}
