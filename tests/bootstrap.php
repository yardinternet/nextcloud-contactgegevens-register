<?php

/**
 * SPDX-FileCopyrightText: 2026 Marc Schwartz
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../tests/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

\OC_App::loadApp(OCA\ContactgegevensRegister\AppInfo\Application::APP_ID);
OC_Hook::clear();
