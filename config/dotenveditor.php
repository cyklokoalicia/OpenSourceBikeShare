<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Path configuration
    |--------------------------------------------------------------------------
    |
    | Change the paths, so they fit your needs
    |
    */
    'pathToEnv'         =>  base_path() . '/.env',
    'backupPath'        =>  storage_path() . '/app/backups/dotenv-editor/', // Make sure, you have a "/" at the end

    /*
    |--------------------------------------------------------------------------
    | GUI-Settings
    |--------------------------------------------------------------------------
    |
    | Here you can set the different parameter for the view, where you can edit
    | .env via a graphical interface.
    |
    | Comma-separate your different middlewares.
    |
    */

    // Activate or deactivate the graphical interface
    'activated'         => true,

    // Set the base-route. All requests start here
    'route'             =>  '/app/enveditor',

    // middleware and middlewwaregroups. Add your own middleware if you want.
    'middleware'        => ['web'/*, 'jwt.auth', 'role:admin'*/],
    'middlewareGroups'  => []
];
