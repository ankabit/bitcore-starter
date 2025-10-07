<?php

declare(strict_types=1);

use BitCore\Application\Services\Settings\SystemConfig;
use BitCore\Foundation\Events\Dispatcher;
use BitCore\Foundation\Translation\Translator;
use BitCore\Kernel\App;
use BitCore\Application\Services\FileUploader;
use BitCore\Application\Services\Settings\SettingsInterface;
use BitCore\Foundation\Filesystem\FilesystemInterface;
use BitCore\Foundation\Filesystem\FilesystemManager;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use BitCore\Application\Services\UserContext;

function dd()
{
    var_dump(func_get_args());
    exit;
}

/**
 * Get instance of event dispatcher
 * @return Dispatcher
 */
function hooks()
{
    return container()->get(Dispatcher::class);
}

/**
 * Get App Instance
 *
 * @return App|null
 */
function app(): ?App
{
    return App::getInstance();
}

/**
 * Get Container
 *
 * @param string|null $name
 * @return ContainerInterface|mixed
 */
function container(string|null $name = null)
{
    $container = app()->getContainer() ?? null;

    if (!$container) {
        throw new \Exception("container(): Error getting the container", 1);
    }

    if ($name === null) {
        return $container;
    }

    return $container[$name] ?? null;
}

/**
 * Get monolog logger instance.
 *
 * @return LoggerInterface
 */
function logger(): LoggerInterface
{
    return container(LoggerInterface::class);
}

/**
 * Get System Config
 *
 * @return SystemConfig
 */
function config()
{
    return container(SystemConfig::class);
}

/**
 * Get Settings
 *
 * @return SettingsInterface
 */
function settings(): SettingsInterface
{
    // Return our system config if no module as implement and bind the settings interface
    return container(SettingsInterface::class) ?? config();
}

/**
 * Helper function to access the database connection.
 *
 * This function provides a shorthand for retrieving the database connection
 * from the application container. If no connection name is provided, it
 * returns the default connection.
 *
 * @param string|null $name The name of the database connection to use (optional).
 * @return \BitCore\Foundation\Database\Connection The database connection instance.
 */
function db($name = null)
{
    return container('db')->getConnection($name);
}

/**
 * Translate the given message.
 *
 * @param  string|null  $key
 * @param  array  $replace
 * @param  string|null  $locale
 * @return \BitCore\Foundation\Translation\Translator|string|array|null
 */
function trans($key = null, $replace = [], $locale = null)
{
    if (is_null($key)) {
        return container(Translator::class);
    }

    return container(Translator::class)->get($key, $replace, $locale);
}

/**
 * Get the UserContext service instance.
 *
 * @return UserContext
 */
function user(): UserContext
{
    return container(UserContext::class) ?? new UserContext();
}

/**
 * Gets a filesystem disk instance.
 *
 * @param string|null $disk The name of the disk to retrieve.
 * Defaults to 'local' or default set in config
 * @return FilesystemInterface The filesystem disk instance.
 */
function storage($disk = null)
{
    return container(FilesystemManager::class)->disk($disk);
}

/**
 * A helper function to upload a file to the specified directory.
 *
 * This function uses a container to retrieve an instance of the FileUploader class
 * and delegates the file upload task to it.
 *
 * @param string $dictory The target directory where the file will be uploaded.
 * @param UploadedFileInterface $file The uploaded file object to process.
 * @param string $disk The storage disk to use for the upload i.e s3 , local, public e.t.c
 * Default disk will be used when not specified
 * @return string The file path of the uploaded file.
 */
function upload(string $dictory, UploadedFileInterface $file, string|null $disk = null): string
{
    /** @var FileUploader $uploader */
    $uploader = container(FileUploader::class);
    return $uploader->useFilesystem(storage($disk))->uploadFile($dictory, $file);
}

/**
 * Get the path to the base folder
 *
 * @param string $path
 * @return string
 */
function base_path(string $path = ''): string
{
    return (defined('APP_BASE_PATH') ? APP_BASE_PATH : BITCORE_BASE_PATH) . ltrim($path, DIRECTORY_SEPARATOR);
}

/**
 * Get the path to the public folder
 *
 * @param string $path
 * @return string
 */
function public_path(string $path = ''): string
{
    // @todo add filter hook
    return base_path('public/' . ltrim($path, '/'));
}

/**
 * Get the path to the storage folder
 *
 * @param string $path
 * @return string
 */
function storage_path(string $path = ''): string
{
    // @todo add filter hook

    return base_path('storage/' . ltrim($path, '/'));
}

/**
 * Reads and merges configuration arrays from default and optional custom config files.
 *
 * This function loads a configuration array from a file in the default config path
 * (BITCORE_CONFIG_PATH). If a custom config path (BITCORE_CONFIG_PATH) is defined
 * and contains the same file, it merges the custom config into the default config, with
 * custom values overriding defaults. The function expects both config files to return arrays.
 *
 * @param string $file The name of the configuration file to load (e.g., 'settings.php' or 'settings').
 *
 * @return array|callable The merged configuration array from default and optional custom files.
 */
function read_config_array($file, $ext = '.php')
{
    $config = require BITCORE_CONFIG_PATH . $file . $ext;
    if (defined('APP_CONFIG_PATH')) {
        $extraFile = APP_CONFIG_PATH . $file;
        if (file_exists($extraFile)) {
            $extraConfig = require $extraFile;
            if (is_array($extraConfig)) {
                $config = array_merge($config, $extraConfig);
            } else {
                $config = $extraConfig;
            }
        }
    }

    return $config;
}

/**
 * Retrieves the mapping of module directory paths to their corresponding base namespaces.
 *
 * This function initializes a default mapping for the core BitCore modules and optionally
 * appends additional mappings if `APP_MODULES_PATH` and `APP_MODULES_BASE_NAMESPACE` are defined
 * and not empty. If either of the custom constants is empty when defined, a RuntimeException is thrown.
 *
 * @return array<string, string> Associative array where the keys are absolute module directory paths
 *                               and the values are their corresponding base namespaces.
 *
 * @throws \RuntimeException If `APP_MODULES_PATH` or `APP_MODULES_BASE_NAMESPACE` are defined but empty.
 */
function get_module_path_namespace_map(): array
{
    // Initialize the array with the default module path and namespace
    $modulePathNamespaceMap = [
        BITCORE_BASE_PATH . 'src/modules/' => '\\BitCore\\Modules\\',
    ];

    // Add custom module path and namespace if both constants are defined
    if (defined('APP_MODULES_PATH') && defined('APP_MODULES_BASE_NAMESPACE')) {
        if (empty(APP_MODULES_PATH) || empty(APP_MODULES_BASE_NAMESPACE)) {
            throw new \RuntimeException('APP_MODULES_PATH or APP_MODULES_BASE_NAMESPACE is empty');
        }
        $modulePathNamespaceMap[APP_MODULES_PATH] = APP_MODULES_BASE_NAMESPACE;
    }

    return $modulePathNamespaceMap;
}

/**
 * Get the directory path where modules will be uploaded.
 *
 * @param bool $absolute  Whether to return the absolute path or the relative path from the base path.
 *
 * @return string  The module upload directory path.
 */
function get_module_upload_dir($absolute = false)
{
    $absolutePath = array_key_last(get_module_path_namespace_map());
    if ($absolute) {
        return $absolutePath;
    }

    return str_replace(base_path(), '', $absolutePath);
}

function base_url($path = '')
{
    // Retrieve the Request object from the Slim container
    $request = app()->getRequest();

    // Get the URI from the request
    $uri = $request->getUri();

    $host = $uri->getHost() ?: 'localhost';

    // Construct the base URL
    $baseUrl = $uri->getScheme() . '://' . $host;

    // Add port if it's not the default (80 for HTTP, 443 for HTTPS)
    $port = $uri->getPort();
    if ($port && !in_array($port, [80, 443])) {
        $baseUrl .= ':' . $port;
    }

    // Append the base path if it exists
    $basePath = app()->getBasePath();
    if ($basePath) {
        $baseUrl .= $basePath;
    }

    // Append the provided path
    if ($path) {
        $baseUrl .= '/' . ltrim($path, '/');
    }

    return $baseUrl;
}
