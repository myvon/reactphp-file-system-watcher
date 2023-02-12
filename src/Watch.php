<?php

namespace Myvon\Watcher;

use Myvon\Watcher\Exception\WatcherError;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

class Watch
{
    const EVENT_TYPE_FILE_CREATED = 'fileCreated';
    const EVENT_TYPE_FILE_UPDATED = 'fileUpdated';
    const EVENT_TYPE_FILE_DELETED = 'fileDeleted';
    const EVENT_TYPE_DIRECTORY_CREATED = 'directoryCreated';
    const EVENT_TYPE_DIRECTORY_DELETED = 'directoryDeleted';

    protected float $interval = 0.5;

    protected array $paths = [];

    /** @var callable[] */
    protected array $onFileCreated = [];

    /** @var callable[] */
    protected array $onFileUpdated = [];

    /** @var callable[] */
    protected array $onFileDeleted = [];

    /** @var callable[] */
    protected array $onDirectoryCreated = [];

    /** @var callable[] */
    protected array $onDirectoryDeleted = [];

    /** @var callable[] */
    protected array $onClose = [];

    /** @var callable[] */
    protected array $onAny = [];

    protected bool $stop = false;

    protected bool $running = false;

    protected bool $closeCallbacksCalled = false;

    protected Process $watcher;

    protected \Closure $shouldContinue;

    public static function path(string $path): self
    {
        return (new self())->setPaths($path);
    }

    public static function paths(...$paths): self
    {
        return (new self())->setPaths($paths);
    }

    public function __construct()
    {
        $this->shouldContinue = fn () => true;
    }

    public function setPaths(string | array $paths): self
    {
        if (is_string($paths)) {
            $paths = func_get_args();
        }

        $this->paths = $paths;

        return $this;
    }

    public function onFileCreated(callable $onFileCreated): self
    {
        $this->onFileCreated[] = $onFileCreated;

        return $this;
    }

    public function onFileUpdated(callable $onFileUpdated): self
    {
        $this->onFileUpdated[] = $onFileUpdated;

        return $this;
    }

    public function onFileDeleted(callable $onFileDeleted): self
    {
        $this->onFileDeleted[] = $onFileDeleted;

        return $this;
    }

    public function onDirectoryCreated(callable $onDirectoryCreated): self
    {
        $this->onDirectoryCreated[] = $onDirectoryCreated;

        return $this;
    }

    public function onDirectoryDeleted(callable $onDirectoryDeleted): self
    {
        $this->onDirectoryDeleted[] = $onDirectoryDeleted;

        return $this;
    }

    public function onAnyChange(callable $callable): self
    {
        $this->onAny[] = $callable;

        return $this;
    }

    public function onClose(callable $callable): self
    {
        $this->onClose[] = $callable;

        return $this;
    }

    public function setIntervalTime(int $interval): self
    {
        $this->interval = $interval;

        return $this;
    }

    public function shouldContinue(\Closure $shouldContinue): self
    {
        $this->shouldContinue = $shouldContinue;

        return $this;
    }

    public function stop(): self
    {
        $this->stop = true;

        return $this;
    }

    public function isRunning(): bool {
        return $this->watcher->isRunning();
    }

    public function start(LoopInterface $loop = null, float $interval = null): self
    {
        $this->watcher = $this->getWatchProcess();

        if(null === $loop) {
            $loop = Loop::get();
        }
        if(null !== $interval) {
            $this->interval = $interval;
        }

        $this->watcher->start($loop, $this->interval);

        $this->running = true;

        $timer = $loop->addPeriodicTimer(0.1, function() {
            if(!$this->stop) {
                $this->stop = !($this->shouldContinue)();
            }

            if ($this->stop && $this->running) {
                // Workaround, teriminate() seems to not have any effect ?
                $this->watcher->stdin->write("stop");
                $this->watcher->terminate(); // still using it, just in case
                $this->running = false;
            }

            // Check here if still running because exit event seems to not be triggered by ChildProcess
            if(!$this->watcher->isRunning() && !$this->closeCallbacksCalled) {
                $this->closeCallbacksCalled = true; // Avoid callbacks to be called multiple times
                foreach ($this->onClose as $onCloseCallable) {
                    $onCloseCallable();
                }
            }
        });


        $this->onClose(function() use($loop, $timer) {
            $loop->cancelTimer($timer); // cancel timer
        });

        $this->watcher->stdout->on('error', function(\Exception $exception) {
            throw WatcherError::make($exception);
        });

        $this->watcher->stdout->on("data", function($chunk) {
            $this->actOnOutput($chunk);
        });

        return $this;
    }

    protected function getWatchProcess(): Process
    {
        $command = [
            'node',
            realpath(__DIR__ . '/../bin/file-watcher.js'),
            escapeshellarg(json_encode($this->paths)),
        ];

        $process = new Process(implode(' ', $command));

        return $process;
    }

    protected function actOnOutput(string $output): void
    {
        $lines = explode(PHP_EOL, $output);

        $lines = array_filter($lines);

        foreach ($lines as $line) {
            [$type, $path] = explode(' - ', $line, 2);

            $path = trim($path);

            match ($type) {
                static::EVENT_TYPE_FILE_CREATED => $this->callAll($this->onFileCreated, $path),
                static::EVENT_TYPE_FILE_UPDATED => $this->callAll($this->onFileUpdated, $path),
                static::EVENT_TYPE_FILE_DELETED => $this->callAll($this->onFileDeleted, $path),
                static::EVENT_TYPE_DIRECTORY_CREATED => $this->callAll($this->onDirectoryCreated, $path),
                static::EVENT_TYPE_DIRECTORY_DELETED => $this->callAll($this->onDirectoryDeleted, $path),
            };

            foreach ($this->onAny as $onAnyCallable) {
                $onAnyCallable($type, $path);
            }
        }
    }

    protected function callAll(array $callables, string $path): void
    {
        foreach ($callables as $callable) {
            $callable($path);
        }
    }
}