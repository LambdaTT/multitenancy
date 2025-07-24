<?php

namespace Multitenancy\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class GenerateSecondTenantForTests extends Migration
{
  public function apply()
  {
    $this->onDatabase('multitenancy')
      ->Table('MTN_TENANT')->Seed(1)
      ->onlyRunInEnvs(['dev', 'development', 'hml', 'test', 'qa'])
      ->onField('ds_key', true)->setFixedValue('tenant2')
      ->onField('ds_name')->setFixedValue('Localhost Dev Tenant 2')
      ->onField('ds_database_name')->setFixedValue('localhost_dev2')
      ->onField('ds_database_user')->setFixedValue('root')
      ->onField('ds_database_pass')->setFixedValue('root');
  }
}
