<?php

namespace Multitenancy\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;

class Commands extends Cli
{
  public function init()
  {
    $this->addCommand('tenants:list', function ($args) {
      $getRows = function ($params) {
        return $this->getService('multitenancy/tenant')->list($params);
      };

      $columns = [
        'id_mnt_tenant'           => 'ID',
        'dt_created'              => 'Created At',
        'ds_name'                => 'Tenant Name',
      ];

      $this->getService('utils/misc')->printDataTable("Modules List", $getRows, $columns, $args);
    });

    $this->addCommand('tenants:create', function () {
      Utils::printLn("Welcome to the Tenant Create Command!");
      Utils::printLn("This command will help you add a new tenant.");
      Utils::printLn();
      Utils::printLn(" >> Please follow the prompts to define your tenant informations.");
      Utils::printLn();
      Utils::printLn("  >> New Tenant:");
      Utils::printLn("------------------------------------------------------");

      $tenant = $this->getService('utils/clihelper')->inputForm([
        'ds_name' => [
          'label' => 'Tenant Name',
          'required' => true,
          'length' => 100,
        ],
        'ds_database_name' => [
          'label' => 'Database Name',
          'required' => true,
          'length' => 100,
        ],
        'ds_database_user_main' => [
          'label' => 'Database Main User',
          'required' => true,
          'length' => 100,
        ],
        'ds_database_pass_main' => [
          'label' => 'Database Main User Password',
          'required' => true,
          'length' => 100,
        ],
        'ds_database_user_readonly' => [
          'label' => 'Database Read-Only User',
          'required' => true,
          'length' => 100,
        ],
        'ds_database_pass_readonly' => [
          'label' => 'Database Read-Only User Password',
          'required' => true,
          'length' => 100,
        ],
      ]);

      $tenant->ds_key = $this->getService('utils/misc')->stringToSlug($tenant->ds_name);

      $record = $this->getDao('MDC_MODULE')
        ->insert($tenant);

      Utils::printLn("  >> Tenant added successfully!");
      foreach ($record as $key => $value) {
        Utils::printLn("    -> {$key}: {$value}");
      }
    });

    $this->addCommand('tenants:remove', function () {
      Utils::printLn("Welcome to the Tenant Removal Command!");
      Utils::printLn();
      Utils::printLn('Enter the Tenant ID you wish to remove:');
      $tenantId = $this->getService('utils/misc')->persistentCliInput(
        function ($v) {
          return !empty($v) && is_numeric($v);
        },
        "Tenant ID must be an integer and cannot be empty or zero. Please try again:"
      );

      $this->getDao('MNT_TENANT')
        ->filter('id_mnt_tenant')->equalsTo($tenantId)
        ->delete();
      Utils::printLn("  >> Tenant '{$tenantId}' removed successfully!");
    });
  }
}
