<?php
/**
 * Package exception.
 *
 * @package Cherrylift
 */

namespace Cherrylift\Package;

use Cherrylift\Exception\SyncException;

/**
 * Thrown when a package cannot be written, or is invalid or unsafe to read.
 */
class PackageException extends SyncException {
}
