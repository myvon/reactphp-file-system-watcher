# reactphp-file-system-watcher
File System Watcher made with ReactPHP EventLoop and ChildProcess.

This is entirely based on [spatie/file-system-watcher](https://github.com/spatie/file-system-watcher) and
adapted from [symfony/process](https://github.com/symfony/process) to [react/child-process](https://github.com/reactphp/child-process)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/myvon/reactphp-file-system-watcher.svg?style=flat-square)](https://packagist.org/packages/myvon/reactphp-file-system-watcher)
[![Tests](https://github.com/myvon/reactphp-file-system-watcher/actions/workflows/run-test.yml/badge.svg)](https://github.com/myvon/reactphp-file-system-watcher/actions/workflows/run-test.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/myvon/reactphp-file-system-watcher.svg?style=flat-square)](https://packagist.org/packagesmyvon/reactphp-file-system-watcher)

# Watch changes in the file system using PHP

This package allows you to react to all kinds of changes in the file system.

It use [react/event-loop](https://github.com/reactphp/event-loop) and [react/child-process](https://github.com/reactphp/child-process) to run without blocking the rest of your code (see [ReactPHP](https://reactphp.org/) for more information). 


Here's how you can run code when a new file gets added.

```php
use Myvon\Watcher\Watch;

Watch::path($directory)
    ->onFileCreated(function (string $newFilePath) {
        // do something...
    })
    ->start();
```

## Installation

You can install the package via composer:

```bash
composer require myvon/reactphp-file-system-watcher
```

In your project, you should have the JavaScript package [`chokidar`](https://github.com/paulmillr/chokidar) installed. You can install it via npm

```bash
npm install chokidar
```

or Yarn

```bash
yarn add chokidar
```

## Usage

Here's how you can start watching a directory and get notified of any changes.

```php
use Myvon\Watcher\Watch;

$watcher = Watch::path($directory)
    ->onAnyChange(function (string $type, string $path) {
        if ($type === Watch::EVENT_TYPE_FILE_CREATED) {
            echo "file {$path} was created";
        }
    })
    ->start();
```

You can pass as many directories as you like to `path`.

To start watching, call the `start` method. 

To make sure that the watcher keeps watching in production, monitor the script or command that starts it with something like [Supervisord](http://supervisord.org).

### Detected the type of change

The `$type` parameter of the closure you pass to `onAnyChange` can contain one of these values:

- `Watcher::EVENT_TYPE_FILE_CREATED`: a file was created
- `Watcher::EVENT_TYPE_FILE_UPDATED`: a file was updated
- `Watcher::EVENT_TYPE_FILE_DELETED`: a file was deleted
- `Watcher::EVENT_TYPE_DIRECTORY_CREATED`: a directory was created
- `Watcher::EVENT_TYPE_DIRECTORY_DELETED`: a directory was deleted

### Listening for specific events

To handle file systems events of a certain type, you can make use of dedicated functions. Here's how you would listen for file creations only.

```php
use Myvon\Watcher\Watch;

Watch::path($directory)
    ->onFileCreated(function (string $newFilePath) {
        // do something...
    });
```

These are the related available methods:

- `onFileCreated()`: accepts a closure that will get passed the new file path
- `onFileUpdated()`: accepts a closure that will get passed the updated file path
- `onFileDeleted()`: accepts a closure that will get passed the deleted file path
- `onDirectoryCreated()`: accepts a closure that will get passed the created directory path
- `onDirectoryDeleted()`: accepts a closure that will get passed the deleted directory path
- `onClose()`: accepts a closure that will be called when watcher is stopped
### Watching multiple paths

You can pass multiple paths to the `paths` method.

```php
use Myvon\Watcher\Watch;

Watch::paths($directory, $anotherDirectory);
```

### Performing multiple tasks

You can call `onAnyChange`, 'onFileCreated', ... multiple times. All given closures will be performed

```php
use Myvon\Watcher\Watch;

Watch::path($directory)
    ->onFileCreated(function (string $newFilePath) {
        // do something on file creation...
    })
    ->onFileCreated(function (string $newFilePath) {
        // do something else on file creation...
    })
    ->onAnyChange(function (string $type, string $path) {
        // do something...
    })
    ->onAnyChange(function (string $type, string $path) {
        // do something else...
    })
    // ...
```

### Stopping the watcher gracefully

By default, the watcher will continue indefinitely when started. 
There is two ways to gracefully stop the watcher :

- you can call `shouldContinue` and pass it a closure. If the closure returns a falsy value, the watcher will stop. The given closure will be executed every 0.5 second.

```php
use Myvon\Watcher\Watch;

Watch::path($directory)
    ->shouldContinue(function () {
        // return true or false
    })
    // ...
```
- you can call the `stop` method anywhere in your code 

```php
use Myvon\Watcher\Watch;

$watcher = Watch::path($directory);
// ...
$watcher->stop();
```
### Change the speed of watcher

By default, the changes are tracked every 0.5 seconds, however you could change that.

```php
use Myvon\Watcher\Watch;

Watch::path($directory)
    ->setIntervalTime(0.1) //unit is seconds therefore -> 0.1s
    // ...rest of your methods
```

You can also specify the interval directly on the `start`. 


```php
use Myvon\Watcher\Watch;

Watch::path($directory)
    ->start(null, 0.1); //unit is seconds therefore -> 0.1s
    // ...rest of your methods
```

### Using another loop

By default, the watcher will use the default loop by calling `Loop:get()`. If needed, you can use another loop implemting `LoopInterface` interface of ReactPHP by passing it as the first argument of `start`:

```php
use Myvon\Watcher\Watch;

$loop = new MyCustomLoop();

Watch::path($directory)
    ->start($loop);
```
Notice: the watcher will register the needed timer but won't start the loop, don't forget to start it.

You also can look into `test/WatchTest.php` to see how it is used to have one loop by test 
## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](https://github.com/myvon/reactphp-file-system-watcher/blob/main/CONTRIBUTING.md) for details.

## Credits

- All credits goes to [Spatie](https://github.com/spatie) for the original [spatie/file-system-watcher](https://github.com/spatie/file-system-watcher) library 

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

