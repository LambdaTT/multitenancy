<?php

namespace Multitenancy\Migrations;

use SplitPHP\DbManager\Migration;

class GenerateDevLocalTenant extends Migration
{
  public function apply()
  {
    $this->onDatabase('multitenancy')
      ->Table('MTN_TENANT')->Seed(1)
      ->onlyRunInEnvs(['development'])
      ->onField('ds_key', true)->setFixedValue('localhost')
      ->onField('ds_name')->setFixedValue('Localhost Dev Tenant')
      ->onField('ds_database_name')->setFixedValue('localhost_dev')
      ->onField('ds_database_user')->setFixedValue('lambdatt_adm')
      ->onField('ds_database_pass')->setFixedValue('@H7t846m2');
  }
}
