<?php
declare(strict_types=1);
namespace Nemesis\Database;

abstract class Migration {
    abstract public function up();
    abstract public function down();
}
