<?php

namespace Acquia\Blt\Robo\Commands\Generate;

use Acquia\Blt\Robo\BltTasks;
use AcquiaCloudApi\CloudApi\Client;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines commands in the "generate:aliases" namespace.
 */
class AliasesCommand extends BltTasks {

  /** @var \Acquia\Cloud\Api\CloudApiClient*/
  protected $cloudApiClient;

  /**
   * @var string
   */
  protected $appId;

  /**
   * @var string
   */
  protected $cloudConfDir;

  /**
   * @var string
   */
  protected $cloudConfFileName;

  /**
   * @var string
   */
  protected $cloudConfFilePath;

  /**
   * Generates new Acquia site aliases for Drush.
   *
   * @command generate:aliases:acquia
   *
   */
  public function generateAliasesAcquia() {

    $this->cloudConfDir = $_SERVER['HOME'] . '/.acquia';
    $this->setAppId();
    $this->cloudConfFileName = 'cloud_api.conf';
    $this->cloudConfFilePath = $this->cloudConfDir . '/' . $this->cloudConfFileName;

    $cloudApiConfig = $this->loadCloudApiConfig();
    $this->setCloudApiClient($cloudApiConfig->key, $cloudApiConfig->secret);

    $this->say("<info>Gathering site info from Acquia Cloud.</info>");
    $site = $this->cloudApiClient->application($this->appId);

    $error = FALSE;
    try {
      $this->getSiteAliases($site, $errors);
    }
    catch (\Exception $e) {
      $error = TRUE;
      $this->logger->error("Did not write aliases for $site->name. Error: " . $e->getMessage());
    }
    if (!$error) {
      $this->say("<info>Aliases were written, type 'drush sa' to see them.</info>");
    }
  }

  protected function setAppId() {
    if ($app_id = $this->getConfigValue('cloud.appId')) {
      $this->appId = $app_id;
    }
    else {
      $this->appId = $this->askRequired('Please enter your Acquia Cloud application ID');
      // @TODO write the app ID to project.yml.
    }
  }

  /**
   * @return array
   */
  protected function loadCloudApiConfig() {
    if (!$config = $this->loadCloudApiConfigFile()) {
      $config = $this->askForCloudApiCredentials();
    }
    return $config;
  }

  /**
   * @return array
   */
  protected function loadCloudApiConfigFile() {
    return json_decode(file_get_contents($this->cloudConfFilePath));
  }

  /**
   *
   */
  protected function askForCloudApiCredentials() {
    $key = $this->askRequired('Please enter your Acquia cloud API key:');
    $secret = $this->askRequired('Please enter your Acquia cloud API secret:');
    do {
      $this->setCloudApiClient($key, $secret);
      $cloud_api_client = $this->getCloudApiClient();
    } while (!$cloud_api_client);
    $config = array(
      'key' => $key,
      'secret' => $secret,
    );
    $this->writeCloudApiConfig($config);
    return $config;
  }

  /**
   * @param $config
   */
  protected function writeCloudApiConfig($config) {
    mkdir($this->cloudConfDir);
    file_put_contents($this->cloudConfFilePath, json_encode($config));
    $this->say("Credentials were written to {$this->cloudConfFilePath}.");
  }

  protected function setCloudApiClient($key, $secret) {
    try {
      $cloud_api = Client::factory(array(
        'key' => $key,
        'secret' => $secret,
      ));
      // We must call some method on the client to test authentication.
      $cloud_api->applications();
      $this->cloudApiClient = $cloud_api;
      return $cloud_api;
    }
    catch (\Exception $e) {
      // @todo this is being thrown after first auth. still works? check out.
      $this->logger->error('Failed to authenticate with Acquia Cloud API.');
      $this->logger->error('Exception was thrown: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * @return \Acquia\Cloud\Api\CloudApiClient
   */
  protected function getCloudApiClient() {
    return $this->cloudApiClient;
  }

  /**
   * @param $site SiteNames[]
   */
  protected function getSiteAliases($site, &$errors) {
    // Gather our environments.
    $environments = $this->cloudApiClient->environments($site->uuid);
    $this->say('<info>Found ' . count($environments) . ' environments for site ' . $site->name . ', writing aliases...</info>');
    // Lets split the site name in the format ac-realm:ac-site.
    $site_split = explode(':', $site->hosting->id);
    $siteRealm = $site_split[0];
    $siteID = $site_split[1];
    // Loop over all environments.
    foreach ($environments as $env) {
      // Build our variables in case API changes.
      $envName = $env->name;
      $uri = $env->domains[0];
      $ssh_split = explode('@', $env->sshUrl);
      $remoteHost = $ssh_split[1];
      $remoteUser = $ssh_split[0];
      $docroot = '/var/www/html/' . $siteID . '.' . $envName . '/docroot';
      $aliases[$envName] = array(
        'root' => $docroot,
        'uri' => $uri,
        'host' => $remoteHost,
        'user' => $remoteUser,
      );
    }
    $this->writeSiteAliases($siteID, $aliases);
  }

  protected function writeSiteAliases($site_id, $aliases) {
    $filePath = $this->getConfigValue('repo.root') . '/drush/site-aliases' . '/' . $site_id . '.alias.yml';
    if (file_exists($filePath)) {
      if (!$this->confirm("File $filePath already exists and will be overwritten. Continue?")) {
        throw new \Exception("Aborted at user request");
      }
    }
    file_put_contents($filePath, Yaml::dump($aliases, 3, 2));
    return $filePath;
  }

}
