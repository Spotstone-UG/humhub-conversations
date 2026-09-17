<?php
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

$humhubRoot = getenv('HUMHUB_ROOT');
if (!$humhubRoot) {
    fwrite(STDERR, "Set HUMHUB_ROOT to a HumHub CE 1.18.5 installation.\n");
    exit(2);
}

$required = [
    'protected/humhub/modules/content/components/ContentActiveRecord.php',
    'protected/humhub/modules/content/components/ContentContainerController.php',
    'protected/humhub/modules/file/widgets/Upload.php',
    'protected/humhub/modules/file/widgets/ShowFiles.php',
    'protected/humhub/modules/like/widgets/LikeLink.php',
    'protected/humhub/modules/user/components/BaseAccountController.php',
];

foreach ($required as $path) {
    if (!is_file(rtrim($humhubRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path))) {
        fwrite(STDERR, "HumHub extension point missing: $path\n");
        exit(1);
    }
}

echo "HumHub extension points required by Conversations are available.\n";

