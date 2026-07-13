<?php

return json_decode((string) file_get_contents(__DIR__ . '/plugin.json'), true)['routes'] ?? [];
