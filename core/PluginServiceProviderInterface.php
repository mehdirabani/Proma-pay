<?php

interface PluginServiceProviderInterface
{
    public function register(PluginManager $manager, array $manifest);

    public function boot(PluginManager $manager, array $manifest);

    public function install(PluginManager $manager, array $manifest);

    public function activate(PluginManager $manager, array $manifest);

    public function deactivate(PluginManager $manager, array $manifest);

    public function update(PluginManager $manager, array $manifest);

    public function uninstall(PluginManager $manager, array $manifest, $purge = false);

    public function healthCheck(PluginManager $manager, array $manifest);
}
