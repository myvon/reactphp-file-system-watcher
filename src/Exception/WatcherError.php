<?php

namespace Myvon\Watcher\Exception;

use React\ChildProcess\Process;

class WatcherError extends \Exception
{
    public static function make(\Exception $exception): self
    {
        return new self("Could not watch files. Watcher Error : " . $exception->getMessage(), 0, $exception);
    }
}