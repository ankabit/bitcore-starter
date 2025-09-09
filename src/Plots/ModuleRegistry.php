<?php

namespace BitCore\Application\Services\Modules;

use BitCore\Foundation\Container;
use Psr\Log\LoggerInterface;

/**
 * Manages the loading and registration of modules within the application.
 */
class ModuleRegistry
{
    /**
     * @var array The path to the modules directory and namespace map.
     */
    protected $modulePathNamespaceMap;

    /**
     * @var ModuleInterface[] $modulesList An cached array to store the loaded modules.
     */
    protected $modulesList = [];

    /**
     * @var LoggerInterface The application logger.
     */
    protected LoggerInterface $logger;


    /**
     * Constructs a new ModuleRegistry instance.
     *
     * @param LoggerInterface $logger The application logger instance.
     * @param array $modulePathNamespaceMap The path to the modules directory.
     */
    public function __construct(LoggerInterface $logger, array $modulePathNamespaceMap)
    {
        $this->modulePathNamespaceMap = $modulePathNamespaceMap;
        $this->logger = $logger;
    }

    /**
     * Gets a list of all available modules sorted by priority
     *
     * @return ModuleInterface[] An array of ModuleInterface objects,
     *                          where the key is the module name and the value is the module instance.
     */
    public function getModules()
    {
        if (empty($this->modulesList)) {
            $this->loadModules();
        }

        return $this->modulesList;
    }

    /**
     * Gets a list of all available modules sorted by priority
     *
     * @param array $modulesToLoad An array contains id of modules to load (optional).
     *
     * @return ModuleInterface[] An array of ModuleInterface objects,
     *                          where the key is the module name and the value is the module instance.
     */
    public function loadModules($modulesToLoad = [])
    {
        foreach ($this->modulePathNamespaceMap as $modulesPath => $baseNamespace) {
            // Use DirectoryIterator to iterate through the modules directory
            $iterator = new \DirectoryIterator($modulesPath);

            foreach ($iterator as $item) {
                if ($item->isDir() && !$item->isDot()) {
                    $folderPath = $item->getPathname();
                    $moduleName = basename($folderPath);

                    if (!empty($modulesToLoad) && !in_array($moduleName, $modulesToLoad)) {
                        continue;
                    }

                    $moduleClass = $baseNamespace . $moduleName . '\\' . $moduleName;

                    if (class_exists($moduleClass)) {
                        $this->modulesList[$moduleName] = new $moduleClass(
                            $modulesPath,
                            $baseNamespace
                        );
                    } else {
                        // Handle the error gracefully
                        // (e.g., log the error instead of exiting)
                        $this->logger->error("Error loading module: $moduleClass");
                    }
                }
            }
        }

        // Sort modules by priority (in ascending order)
        uasort($this->modulesList, function ($a, $b) {
            // Sort based on getPriority(), lower priority value is more important
            return $a->getPriority() <=> $b->getPriority();
        });

        return $this->modulesList;
    }

    /**
     * Extracts the module ID from a given file path and returns the corresponding module.
     *
     * @param string $filePath The file path to analyze.
     * @return ModuleInterface|null The module associated with the extracted module ID, or null if not found.
     */
    public function findModuleFromFilePath($filePath)
    {
        // Extract the part after MODULES_PATH and split by directory separator
        $relativePath = str_replace(array_keys($this->modulePathNamespaceMap), '', $filePath);
        $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
        $moduleId = $parts[0] ?? null;

        return $moduleId ? $this->findModuleById($moduleId) : null;
    }

    /**
     * Retrieves a module by its ID from the modules collection.
     * It attempt to match using class_basename incase the entry file class name is passed.
     *
     * @param string $moduleId The ID of the module to find.
     * @return ModuleInterface|null The module associated with the given ID, or null if not found.
     */
    public function findModuleById(string $moduleId)
    {
        return $this->getModules()[$moduleId] ??
            $this->getModules()[class_basename($moduleId)] ?? null;
    }

    /**
     * Gets the paths to the migration directories of all modules.
     *
     * @return array An array of paths to migration directories.
     */
    public function getMigrationPaths()
    {
        $paths = [];
        foreach ($this->modulePathNamespaceMap as $modulesPath => $baseNamesapce) {
            $paths = array_merge(
                $paths,
                glob($modulesPath . '/*/Database/Migrations')
            );
        }

        return $paths;
    }

    /**
     * Loads and registers all modules.
     * It also attempt of inject the module instance into the container.
     *
     * @param Container $container The application container.
     * @param array|null $modules An optional array of modules to load specifically.
     * @return void
     */
    public function registerModules(Container $container, array|null $modules = null): void
    {
        // If no modules are provided, use the default ones
        $modules = $modules === null ? $this->getModules() : $modules;

        // Iterate through each module after sorting
        foreach ($modules as $module => $instance) {
            /** @var ModuleInterface $instance */

            $instance->beforeRegister();

            // Register the module with the container
            $instance->register();

            // Inject the module into the container if it hasn't been done already
            // @todo remove below code has we do not want to inject module instance into repo
            //if (!$container->has($instance::class)) {
            //    $container->singleton($instance::class, function () use ($instance) {
            //        return $instance;
            //    });
            //}
        }
    }

    /**
     * Loads and registers all modules.
     * It also attempt of inject the module instance into the container.
     *
     * @param Container $container The application container.
     * @param array|null $modules An optional array of modules to load specifically.
     * @return void
     */
    public function bootModules(Container $container, array|null $modules = null): void
    {
        // If no modules are provided, use the default ones
        $modules = $modules === null ? $this->getModules() : $modules;

        // Iterate through each module after sorting
        foreach ($modules as $module => $instance) {
            /** @var ModuleInterface $instance */

            $instance->beforeBoot();

            // Register the module with the container
            $instance->boot();
        }
    }
}
