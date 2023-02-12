<?php

use Spatie\TemporaryDirectory\TemporaryDirectory;
use Myvon\Watcher\Watch;

uses(PHPUnit\Framework\TestCase::class);


beforeEach(function () {
    $this->testDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'testDirectory';

    $this->loop = new \React\EventLoop\StreamSelectLoop();
    (new TemporaryDirectory($this->testDirectory))->empty();

    $this->recordedEvents = [];

    $this->i = 0;

    $this->watcher = Watch::path($this->testDirectory);
});

afterEach(function() {
    (new TemporaryDirectory($this->testDirectory))->empty();
    (new TemporaryDirectory($this->testDirectory))->delete();
});

it('can stop watcher gracefully', function() {
    $watcher = Watch::path($this->testDirectory)
        ->onClose(function() {
            $this->loop->stop();
        })
        ->start($this->loop);

    $this->loop->addTimer(1, function() use($watcher){
        $watcher->stop();
    });

    $this->loop->run();

    expect($watcher->isRunning())->toEqual(false);
});

it('can detect when files get created', function () {
    $this->watcher->onFileCreated(function (string $path) {
            $this->modifiedPath = $path;
        })
        ->onAnyChange(function (string $type, string $path) {
            $this->recordedEvents[] = [$type, $path];
            $this->watcher->stop();
        })->onClose(function() {
            $this->loop->stop();
        })
        ->start($this->loop);

   $this->loop->addTimer(0.5, function()  {
        touch($this->testDirectory . DIRECTORY_SEPARATOR . 'test.txt');
    });

   $this->loop->run();

    expect($this->recordedEvents)->toHaveCount(1)
        ->and($this->recordedEvents[0])->toEqual([
            'fileCreated',
            $this->testDirectory . DIRECTORY_SEPARATOR . 'test.txt',
        ])
        ->and($this->modifiedPath)->toEqual($this->recordedEvents[0][1]);

});

it('can detect when files get updated', function () {
    $stop = false;
    $testFile = $this->testDirectory . DIRECTORY_SEPARATOR . 'test.txt';

    touch($testFile);

    $this->watcher->onFileUpdated(function (string $path) {
            $this->modifiedPath = $path;
        })
        ->onAnyChange(function (string $type, string $path) {
            $this->recordedEvents[] = [$type, $path];
            $this->watcher->stop();
        })->onClose(function() {
            $this->loop->stop();
        })
        ->start($this->loop);

   $this->loop->addTimer(0.5, function() use($testFile) {
        file_put_contents($testFile, 'updated');
    });


   $this->loop->run();

    expect($this->recordedEvents)->toHaveCount(1)
        ->and($this->recordedEvents[0])->toEqual([
            'fileUpdated',
            $testFile,
        ])
        ->and($this->modifiedPath)->toEqual($testFile);

});

it('can detect when files get deleted', function () {
    $stop = false;
    $testFile = $this->testDirectory . DIRECTORY_SEPARATOR . 'test.txt';

    touch($testFile);

    $this->watcher
        ->onFileDeleted(function (string $path) {
            $this->modifiedPath = $path;
        })
        ->onAnyChange(function (string $type, string $path) {
            $this->recordedEvents[] = [$type, $path];
            $this->watcher->stop();
        })->onClose(function() {
            $this->loop->stop();
        })
        ->start($this->loop);

   $this->loop->addTimer(0.5, function() use($testFile) {
        unlink($testFile);
    });

   $this->loop->run();

    expect($this->recordedEvents)->toHaveCount(1)
        ->and($this->recordedEvents[0])->toEqual([
            'fileDeleted',
            $testFile,
        ])
        ->and($this->modifiedPath)->toEqual($testFile);

});

it('can detect when a directory gets created', function () {
    $stop = false;
    $newDirectoryPath = $this->testDirectory . DIRECTORY_SEPARATOR . 'new';

    $this->watcher
        ->onDirectoryCreated(function (string $path) {
            $this->modifiedPath = $path;
        })
        ->onAnyChange(function (string $type, string $path) {
            $this->recordedEvents[] = [$type, $path];
            $this->watcher->stop();
        })->onClose(function() {
            $this->loop->stop();
        })
        ->start($this->loop);

   $this->loop->addTimer(0.5, function() use($newDirectoryPath) {
        mkdir($newDirectoryPath);
    });

   $this->loop->run();

    expect($this->recordedEvents)->toHaveCount(1)
        ->and($this->recordedEvents[0])->toEqual([
            'directoryCreated',
            $newDirectoryPath,
        ])
        ->and($this->modifiedPath)->toEqual($newDirectoryPath);

});

it('can detect when a directory gets deleted', function () {
    $stop = false;
    $directoryPath = $this->testDirectory . DIRECTORY_SEPARATOR . 'new';
    $directory = (new TemporaryDirectory($directoryPath))->empty();

    $this->watcher
        ->onDirectoryDeleted(function (string $path) {
            $this->modifiedPath = $path;
        })
        ->onAnyChange(function (string $type, string $path) {
            $this->recordedEvents[] = [$type, $path];
            $this->watcher->stop();
        })->onClose(function() {
            $this->loop->stop();
        })
        ->start($this->loop);

   $this->loop->addTimer(0.5, function() use($directory) {
        $directory->delete();
    });

   $this->loop->run();

    expect($this->recordedEvents)->toHaveCount(1)
        ->and($this->recordedEvents[0])->toEqual([
            'directoryDeleted',
            $directoryPath,
        ])
        ->and($this->modifiedPath)->toEqual($directoryPath);

});