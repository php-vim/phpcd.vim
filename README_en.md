- [中文](./README.md)
- English

[![asciicast](https://asciinema.org/a/4dzyyjymrguylqt21igxlhhqx.png)](https://asciinema.org/a/4dzyyjymrguylqt21igxlhhqx)

## Introduction

PHPCD (PHP Completion Daemon) is another Vim omni complete engine for PHP.

PHPCD is based on [phpcomplete.vim](https://github.com/shawncplus/phpcomplete.vim) but is faster.

While phpcomplete.vim uses the tags file to fetch the context info, PHPCD uses PHP's Reflection mechanism to fetch the context info, and this is why PHPCD is faster. All the phpcomplete VimL code related the tags file has been droped and reimplemented.

PHPCD consists of two parts. On part is written in VimL (mainly based on phpcomplete.vim), and the other in PHP. ~~The communication between the VimL part and the PHP part relies on NeoVim's MsgPack-RPC mechanism. This is why NeoVim is needed.~~ Both NeoVim and Vim 7.4+ are supported now. Thanks to NoeVims's MsgPack-RPC and Vim's Channel.

##  Feature
 * Fast, Lightweight, Powerful
 * Correct restriction of static or standard methods based on context (show only static methods with `::` and only standard with `->`)
 * Real support for `self::`, `static::`, `parent::` and `$this->` with the aforementioned context restriction
 * Better class detection
     - Recognize `/* @var $yourvar YourClass */`、 `/* @var YourClass $yourvar */` type mark comments
     - Recognize `$instance = new Class;` class instantiations
     - Recognize `$instance = Class::foo()->bar();` method call chain return type use `bar`'s `@return` docblocks
     - Recognize `$date = DateTime::createFromFormat(...)` built-in class return types
     - Recognize type hinting in function prototypes
     - Recognize types in `@param` lines in function docblocks
     - Recognize array of objects via docblock like `$foo[42]->` or for variables created in `foreach`
 * Displays docblock info in the preview for methods and properties
 * Support built-in class support with constants, methods and properties
 * Enhanced jump-to-definition on <kbd>ctrl</kbd>+<kbd>]</kbd>

## Installation & Usage

### System requirement

 1. [PHP 5.3+](http://php.net/)
 2. [PCNTL](http://php.net/manual/en/book.pcntl.php) Extension
 3. [Msgpack 0.5.7+(for NeoVim)](https://github.com/msgpack/msgpack-php) Extension or [JSON(for Vim 7.4+)](http://php.net/manual/en/intro.json.php) Extension
 4. [Composer](https://getcomposer.org/) Project


### Install PHPCD

We recommend you use [Vim-Plug](https://github.com/junegunn/vim-plug/blob/master/README.md) to mange your vim plugins.

With Vim-Plug installed, put the following lines in your vimrc,

```
Plug 'phpvim/phpcd.vim', { 'for': 'php' , 'do': 'composer update --no-dev' }
Plug 'vim-scripts/progressbar-widget' " used for showing the index progress
```

And then execute `:PlugInstall` in the command mode.

## Usage

First, in the project directory, run `composer install --no-dev` to install all the dependent packages and generate the autoload file.

The default PHP command used to run PHP parts of daemon is simply `php`. You may override it by assigning `g:phpcd_php_cli_executable` another value in your `vimrc`, for example:
```
let g:phpcd_php_cli_executable = 'php7.0'
```

Use <kbd>Ctrl</kbd>+<kbd>x</kbd><kbd>Ctrl</kbd>+<kbd>o</kbd> to complete and use <kbd>ctrl</kbd>+<kbd>]</kbd> to go to the defination.


Use <kbd>Ctrl</kbd>+<kbd>x</kbd><kbd>Ctrl</kbd>+<kbd>o</kbd> to complete and use <kbd>ctrl</kbd>+<kbd>]</kbd> to go to the defination.

Good luck :)
