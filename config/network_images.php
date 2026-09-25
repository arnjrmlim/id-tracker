<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Network Image Drive Mapping
    |--------------------------------------------------------------------------
    |
    | When ID images and signatures are stored on a network share, the
    | database may contain paths using a Windows mapped drive letter such as:
    |
    |   Z:\IT_Files\...\Employee Name.png
    |
    | The Apache/PHP process on DC1 does not have a Z: drive mapped.
    | Instead, configure the UNC root that DC1 can access directly:
    |
    |   \\FILE-SERVER\SHARE-NAME
    |
    | The resolver will translate Z:\... → \\FILE-SERVER\SHARE-NAME\...
    | at runtime, without touching the database.
    |
    | Leave NETWORK_ROOT null/empty to disable translation (e.g. on a
    | development machine where Z: is already mapped).
    |
    */

    // The drive letter stored in the database, without trailing backslash.
    // e.g.  Z:
    'network_drive' => 'Z:',

    // The UNC root that the PHP process on the server can access directly.
    // e.g.  \\FILE-SERVER\ID-TRACKER
    // Leave empty on machines where the mapped drive already works.
    'network_root' => '\\\\ims-truenas\\IMS_NAS',

];
