<?php

namespace Multitenancy\Routes;

use SplitPHP\WebService;
use Exception;

class Metadata extends WebService
{
  private const ICON_SIZES = [512, 384, 256, 192, 180, 167, 152, 144, 128, 120, 32];

  public function init()
  {
    $this->setAntiXsrfValidation(false);

    //////////////////////
    // METADATA ENDPOINTS:
    //////////////////////

    $this->addEndpoint('GET', '/v1/appearence', function ($params) {
      $response = $this->getService('settings/settings')->listByContext('appearence');
      $data = [];

      // Construindo os dados
      foreach ($response as $field) {
        $pieces = explode('_', $field->ds_fieldname);
        $selector = $pieces[0];
        $property = implode('-', array_slice($pieces, 1));
        $data[$selector][$property] = $field->tx_fieldvalue;
      }

      // Construindo o CSS
      $css = '';

      // -- Percorre o array e gera as regras CSS
      foreach ($data as $selector => $properties) {
        $filteredProperties = array_filter($properties, function ($value) {
          return !is_null($value) && $value !== '';
        });

        if (!empty($filteredProperties)) {
          $css .= "." . $selector . " {\n";
          foreach ($filteredProperties as $property => $value) {
            $css .= "  $property: $value !important;\n";
          }
          $css .= "}\n\n";
        }
      }

      return $this->response
        ->withStatus(200)
        ->withText($css)
        ->setHeader("Content-Type: text/css");
    });

    $this->addEndpoint('GET', '/v1/tenant-manifest', function () {
      $tenant = $this->getService('multitenancy/tenant')->detect();
      if (empty($tenant)) throw new \Exception("Tenant not found.");

      $pwaSettings = $this->getService('settings/settings')->contextObject('pwa');

      $manifestData = [
        'name' => "{$tenant->ds_name}",
        'short_name' => "{$tenant->ds_name}",
        // 'start_url' => '/',
        'display' => 'standalone',
        "orientation" => "portrait",
        'theme_color' => "#424242",
        'background_color' => "#ffffff",
        'icons' => [
          [
            'src'   => $pwaSettings->icon_128,
            'sizes' => '128x128',
            'type'  => 'image/png'
          ],
          [
            'src'   => $pwaSettings->icon_192,
            'sizes' => '192x192',
            'type'  => 'image/png'
          ],
          [
            'src'   => $pwaSettings->icon_256,
            'sizes' => '256x256',
            'type'  => 'image/png'
          ],
          [
            'src'   => $pwaSettings->icon_384,
            'sizes' => '384x384',
            'type'  => 'image/png'
          ],
          [
            'src'   => $pwaSettings->icon_512,
            'sizes' => '512x512',
            'type'  => 'image/png'
          ],
        ]
      ];

      return $this->response
        ->setHeader('Cache-Control: max-age=86400, public')
        ->withStatus(200)
        ->withData($manifestData);
    });

    $this->addEndpoint('GET', '/v1/tenant-icon/?size?', function ($params) {
      $pwaSettings = $this->getService('settings/settings')->contextObject('pwa');

      $iconContent = file_get_contents($pwaSettings->{"icon_{$params['size']}"});
      if(!empty($iconContent)){
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        echo $iconContent;
      }
      
      http_response_code(200);
      die;
    });

    $this->addEndpoint('GET', '/v1/pwa-settings', function () {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'STT_SETTINGS' => 'R',
      ]);

      $pwaSettings = $this->getService('settings/settings')->contextObject('pwa');

      return $this->response
        ->withStatus(200)
        ->withData($pwaSettings);
    });

    $this->addEndpoint('PUT', '/v1/icon', function () {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'STT_SETTINGS' => 'U',
      ]);

      // Register image's tmp path:
      $tmpFile = $_FILES['icon']['tmp_name'];
      if (!$tmpFile) return $this->response->withStatus(400);

      // Remove previously uploaded icons:
      $this->removePreviousIcons();

      // Retrieve tenant's information:
      $tenant = $this->getService('multitenancy/tenant')->detect();
      if (empty($tenant)) throw new \Exception("Tenant not found.");

      // Cria os ícones dos tamanhos necessários:
      $name = $this->getService('utils/misc')->stringToSlug($tenant->ds_name);
      foreach (self::ICON_SIZES as $size) {
        $filename = "{$name}-icon-{$size}.png";
        $outputPath = ROOT_PATH . "/application/cache/{$filename}";
        $this->resizeImg($tmpFile, $outputPath, $size, $size);
        $this->saveIcon($filename, $size);
        unlink($outputPath);
      }

      return $this->response->withStatus(204);
    });
  }

  private function resizeImg(string $sourcePath, string $outputPath, int $width, int $height): string
  {
    if (!file_exists($sourcePath)) {
      throw new Exception("Source file not found: $sourcePath");
    }

    $originalImage = imagecreatefrompng($sourcePath);
    if (!$originalImage) {
      throw new Exception("Failed to load source PNG image.");
    }

    $resizedImage = imagecreatetruecolor($width, $height);

    // Preserve transparency
    imagesavealpha($resizedImage, true);
    $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
    imagefill($resizedImage, 0, 0, $transparent);

    // Resize
    imagecopyresampled(
      $resizedImage,
      $originalImage,
      0,
      0,
      0,
      0,
      $width,
      $height,
      imagesx($originalImage),
      imagesy($originalImage)
    );

    // Save output
    if (!imagepng($resizedImage, $outputPath)) {
      throw new Exception("Failed to save resized image to: $outputPath");
    }

    imagedestroy($originalImage);
    imagedestroy($resizedImage);

    return $outputPath;
  }

  private function removePreviousIcons()
  {
    $pwaSettings = $this->getService('settings/settings')->contextObject('pwa');

    foreach ($pwaSettings as $key => $value) {
      if (str_contains($key, 'icon') && !empty($value)) {
        $this->getService('filemanager/s3')->deleteObject($value);
      }
    }
  }

  private function saveIcon($filename, $size)
  {
    $path = ROOT_PATH . "/application/cache/{$filename}";
    $filename = 'icn-' . uniqid() . $filename;

    // Save the icon in S3
    $iconUrl = $this->getService('filemanager/s3')
      ->putObject($path, $filename, 'image/png');

    // Update the PWA settings
    $this->getService('settings/settings')->change('pwa', "icon_{$size}", $iconUrl);
  }
}
