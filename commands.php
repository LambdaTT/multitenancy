<?php

namespace Multitenancy\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;

class Commands extends Cli
{
  public function init(): void
  {
    $this->addCommand('tenants:list', function ($args) {
      $getRows = function ($params) {
        return $this->getService('multitenancy/tenant')->list($params);
      };

      $columns = [
        'id_snd_tenant'           => 'ID',
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
        'ds_customkey' => [
          'label' => 'Custom Key (optional)',
          'required' => false,
          'length' => 255,
        ],
      ]);

      $record = $this->getService('multitenancy/tenant')->create((array) $tenant);

      Utils::printLn("  >> Tenant added successfully!");
      foreach ($record as $key => $value) {
        Utils::printLn("    -> {$key}: {$value}");
      }
    });

    $this->addCommand('tenants:remove', function () {
      Utils::printLn("Welcome to the Tenant Removal Command!");
      Utils::printLn();
      Utils::printLn('Enter the Tenant subdomain or custom key you wish to remove:');
      $tenantKey = $this->getService('utils/misc')->persistentCliInput(
        function ($v) {
          return !empty($v);
        },
        "Tenant key cannot be empty. Please try again:"
      );

      $this->getService('multitenancy/tenant')->remove($tenantKey);
      Utils::printLn("  >> Tenant '{$tenantKey}' removed successfully!");
    });

    // Help command
    $this->addCommand('tenants:help', function () {
      /** @var \Utils\Services\CliHelper $helper */
      $helper = $this->getService('utils/clihelper');
      Utils::printLn($helper->ansi(strtoupper("Welcome to the Tenants Help Center!"), 'color: magenta; font-weight: bold'));

      // 1) Define metadata for each command
      $commands = [
        ':tenants:list'   => [
          'usage' => 'multitenancy:tenants:list [--limit=<n>] [--sort-by=<field>] [--sort-direction=<dir>] [--page=<n>]',
          'desc'  => 'Page through existing tenants.',
          'flags' => [
            '--limit=<n>'          => 'Items per page (default 10)',
            '--sort-by=<field>'    => 'Field to sort by',
            '--sort-direction=<d>' => 'ASC or DESC (default ASC)',
            '--page=<n>'           => 'Page number (default 1)',
          ],
        ],
        ':tenants:create' => [
          'usage' => 'multitenancy:tenants:create',
          'desc'  => 'Interactively create a new tenant.',
        ],
        ':tenants:remove' => [
          'usage' => 'multitenancy:tenants:remove',
          'desc'  => 'Delete a tenant by its subdomain or custom key.',
        ],
        ':tenants:help'             => [
          'usage' => 'multitenancy:tenants:help',
          'desc'  => 'Show this help screen.',
        ],
      ];

      // 2) Summary table
      Utils::printLn($helper->ansi("\nAvailable commands:\n", 'color: cyan; text-decoration: underline'));

      $rows = [
        [
          'cmd'  => 'multitenancy:tenants:list',
          'desc' => 'Page through existing tenants',
          'opts' => '--limit, --sort-by, --sort-direction, --page',
        ],
        [
          'cmd'  => 'multitenancy:tenants:create',
          'desc' => 'Interactively create a new tenant',
          'opts' => '(no flags)',
        ],
        [
          'cmd'  => 'multitenancy:tenants:remove',
          'desc' => 'Delete a tenant by subdomain/key',
          'opts' => '(no flags)',
        ],
        [
          'cmd'  => 'multitenancy:tenants:help',
          'desc' => 'Show this help screen',
          'opts' => '(no flags)',
        ],
      ];

      $helper->table($rows, [
        'cmd'  => 'Command',
        'desc' => 'Description',
        'opts' => 'Options',
      ]);

      // 3) Detailed usage lists
      foreach ($commands as $cmd => $meta) {
        Utils::printLn($helper->ansi("\n{$cmd}", 'color: yellow; font-weight: bold'));
        Utils::printLn("  Usage:   {$meta['usage']}");
        Utils::printLn("  Purpose: {$meta['desc']}");

        if (!empty($meta['flags'])) {
          Utils::printLn("  Options:");
          $flagLines = [];
          foreach ($meta['flags'] as $flag => $explain) {
            $flagLines[] = "{$flag}  — {$explain}";
          }
          $helper->listItems($flagLines, false, '    •');
        }
      }

      Utils::printLn(''); // trailing newline
    });
  }
}
