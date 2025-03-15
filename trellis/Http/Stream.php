<?php

/**
 * @package     Clementine\Trellis
 * @author      Clementine Solutions
 * @copyright   Clementine Technology Solutions LLC. (dba Clementine
 *              Solutions). All rights reserved.
 * @link        https://github.com/stevencclements/Trellis-PHP-Framework.git
 * 
 * @version     1.0.0
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Clementine\Trellis\Http;

use Clementine\Trellis\Exceptions\StreamArgumentException;
use Clementine\Trellis\Exceptions\StreamRuntimeException;
use Psr\Http\Message\StreamInterface;

/**
 * `Stream`
 * 
 * Represents a data stream built on top of an underlying file or PHP resource
 * and wraps most common operations, including serializing the entire stream to
 * a string.
 */
class Stream implements StreamInterface
{
    /**
     * @param       resource|null           $resource
     * 
     * The file or PHP resource supporting the stream.
     */
    private mixed $stream = null;

    /**
     * @param       array                   $metadata
     * 
     * The metadata associated with the underlying stream resource.
     */
    private array $metadata = [];

    /**
     * @param       string                  $mode
     * 
     * Specifies the operations to allow for the resource supporting the
     * stream.
     */
    private string $mode = 'r';

    /**
     * @param       bool                    $seekable
     * 
     * Specifies whether or not the stream is seekable.
     */
    private bool $seekable = false;

    /**
     * @param       bool                    $readable
     * 
     * Specifies whether or not the stream is readable.
     */
    private bool $readable = false;

    /**
     * @param       bool                    $writable
     * 
     * Specifies whether or not the stream is writable.
     */
    private bool $writable = false;

    /**
     * @param       int|null                $size
     * 
     * The size of the underlying stream resource in bytes, if known.
     */
    private ?int $size = null;

    /**
     * @param       bool                    $closed
     * 
     * Specifies whether or not the stream has been closed.
     */
    private bool $closed = false;

    /**
     * `__construct` [Constructor]
     * 
     * Create a new instance of the `Stream` class with the specified (or
     * default) properties.
     * 
     * @param       resource|null           $resource
     * 
     * The underlying PHP resource to build the stream on.
     * 
     * @throws      StreamArgumentException
     * 
     * If the underlying file or PHP resource is invalid.
     */
    public function __construct(
        mixed $resource
    ) {
        // Streams should ideally be created using `StreamFactory`, however,
        // the resource passed to the constructor should still be validated
        // for instances where `Stream` is used without the factory.
        if (!is_resource($resource)) {
            
            // If the resource is null, attempt to create a temporary stream.
            if ($resource === null) {
                $resource = fopen('php://temp', 'r+');
        
                if ($resource === false) {
                    /**
                     * @todo    ERROR LOGGING
                     * Log the error once PSR-3 LoggerInterface is implemented.
                     */
                    throw new StreamRuntimeException("Failed to create a temporary stream.");
                }
            } else {
                /**
                 * @todo    ERROR LOGGING
                 * Log the error once PSR-3 LoggerInterface is implemented.
                 */
                throw new StreamArgumentException("The specified resource is invalid.");
            }
        }
        

        $this->stream = $resource;

        try {
            // Attempt to retrieve metadata values from the current stream. Certain
            // metadata values, such as `mode`, `size`, and `url` are only present
            // for certain resource types.
            $this->metadata = stream_get_meta_data($this->stream);
        } catch (\Throwable) {
            throw new StreamRuntimeException(
                "The metadata for the specified resource could not be retrieved."
            );
        }

        // Set class properties immediately based on metadata values.
        $this->mode = $this->metadata['mode'] ?? 'r';
        $this->seekable = $this->metadata['seekable'] ?? false;
        $this->readable = strpbrk($this->mode, 'r+') !== false;
        $this->writable = strpbrk($this->mode, 'waxc+') !== false;

        $stats = fstat($this->stream);
        $this->size = $stats !== false ? ($stats['size'] ?? null) : null;
    }

    /**
     * `__destruct` [Destructor]
     * 
     * Performs cleanup for Stream objects when they go out of scope to prevent
     * memory leaks and other issues.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * `__toString` [Magic Method]
     * 
     * Serializes stream contents into a string from the beginning to the end.
     * 
     * @return      string                  $content
     * 
     * The entire contents of the stream, if seekable, otherwise the contents
     * of the stream from the current position to the end of the stream.
     */
    public function __toString(): string
    {
        if (
            $this->closed ||
            !$this->readable ||
            ftell($this->stream) === 0 ||
            ftell($this->stream) === false
        ) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            return '';
        }

        if ($this->seekable) {
            $this->rewind();
        }

        try {
            $contents = stream_get_contents($this->stream);

            return $contents;
        } catch (\RuntimeException) {
            return '';
        }
    }

    /**
     * `isSeekable`
     * 
     * Specifies whether or not the current stream resource is seekable.
     * 
     * @return      bool                    $seekable
     * 
     * True if the stream is seekable or false if it is not.
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * `isReadable`
     * 
     * Specifies whether or not the current stream is readable.
     * 
     * @return      bool                    $readable
     * 
     * True if the stream is readable or false if it is not.
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * `isWritable`
     * 
     * Specifies whether or not the current stream is writable.
     * 
     * @return      bool                    $writable
     * 
     * True if the stream is writable or false if it is not.
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * `getMetadata`
     * 
     * Retrieves metadata from the current stream, either as an array (when no
     * key is specified), as a string value (if a key is provided and matches
     * a valid metadata value), or null (if the key is invalid).
     * 
     * @param       string                  $key
     * 
     * An optional metadata key used to retrieve a specific value from the
     * stream metadata.
     * 
     * @return      array|string|null       $metadata
     * 
     * An array of metadata values, the specified metadata value, or null if
     * no metadata is found.
     */
    public function getMetadata(?string $key = null): array|string|null
    {
        if ($this->closed) {
            return null;
        }

        if ($key !== null) {
            return $this->metadata[$key] ?? null;
        }

        return $this->metadata;
    }

    /**
     * `getSize`
     * 
     * Retrieve the size of the stream resource in bytes, if known.
     * 
     * @return      int|null                $size
     * 
     * The size of the stream in bytes, if known, or null, if unknown.
     */
    public function getSize(): int|null
    {
        return $this->size ?? null;
    }

    /**
     * `getContents`
     * 
     * Retrieve the remaining content from the stream resource as a string.
     * 
     * @return      string                  $content
     * 
     * The remaining content from the stream as a string.
     * 
     * @throws      StreamRuntimeException
     * 
     * If the stream is closed, invalid, unreadable, empty, or an error
     * occurs during the read operation.
     */
    public function getContents(): string
    {
        if ($this->closed) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            throw new StreamRuntimeException(
                "Failed to read from the current stream. The stream is closed or invalid."
            );
        }

        if (!$this->readable) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            throw new StreamRuntimeException(
                "Failed to read from the current stream. The stream is not readable."
            );
        }

        if (ftell($this->stream) === false) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            throw new StreamRuntimeException(
                "Failed to read from the current stream. The position of the stream pointer could not be determined."
            );
        }

        try {
            $contents = stream_get_contents($this->stream);

            return $contents;
        } catch (\RuntimeException) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            throw new StreamRuntimeException(
                "Failed to read from the current stream. An error occurred during the read operation."
            );
        }
    }

    /**
     * `tell`
     * 
     * Determine the current position of the stream read/write pointer.
     * 
     * @return      int                 $position
     * 
     * The current position of the stream pointer.
     * 
     * @throws      StreamRuntimeException
     * 
     * If the stream is closed or invalid, or if the location of the
     * stream pointer cannot be determined.
     */
    public function tell(): int
    {
        if ($this->closed) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            throw new StreamRuntimeException(
                "Failed to determine the position of the stream pointer. The stream is closed or invalid."
            );
        }

        if (($position = ftell($this->stream)) === false) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            throw new StreamRuntimeException(
                "Failed to determine the position of the stream pointer. An error occurred during the tell operation."
            );
        }

        return $position;
    }

    /**
     * `eof`
     * 
     * Determines whether the position of the stream pointer has reached the
     * end of the current resource.
     * 
     * @return      bool                $endOfFile
     * 
     * True if the pointer has reached the end of the file or false if it has
     * not.
     * 
     * @throws      StreamRuntimeException
     * 
     * If the stream is closed or invalid.
     */
    public function eof(): bool
    {
        if ($this->closed) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            throw new StreamRuntimeException(
                "Failed to determine the position of the stream pointer. The stream is closed or invalid."
            );
        }

        return feof($this->stream);
    }

    /**
     * `seek`
     * 
     * Move the stream pointer to the specified position in the resource.
     * 
     * @param       int                 $offset
     * 
     * The stream offset.
     * 
     * @param       int                 $whence
     * 
     * Specifies how the cursor position is calculated based on the seek
     * offset:
     *   - `SEEK_SET`: Set the position equal to the offset bytes.
     *   - `SEEK_CUR`: Set the position to the current location plus the
     *      offset bytes.
     *   - `SEEK_END`: Set the position to the end of the stream plus the
     *      offset bytes.
     * 
     * @return      void
     * 
     * @throws      StreamRuntimeException
     * 
     * If the stream is closed, invalid, not seekable, or an error occurs
     * during the seek operation.
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($this->closed) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            throw new StreamRuntimeException(
                "Failed to seek in the current stream. The stream is closed or invalid."
            );
        }

        if (!$this->seekable) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            throw new StreamRuntimeException(
                "Failed to seek in the current stream. The stream is not seekable."
            );
        }
        
        if (fseek($this->stream, $offset, $whence) === -1) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            throw new StreamRuntimeException(
                "Failed to seek in the current stream. An error occurred during the seek operation."
            );
        }
    }

    /**
     * `rewind`
     * 
     * Move the stream pointer to the starting position of the stream.
     * 
     * @return      void
     * 
     * @throws      StreamRuntimeException
     * 
     * If the stream is closed, invalid, not seekable, or an error occurs
     * during the seek operation.
     */
    public function rewind(): void
    {
        // Attempt to move the pointer to the beginning of the stream
        try {
            $this->seek(0); // Calls the `seek` method, passing an offset of 0
        } catch (StreamRuntimeException $exception) {
            /**
             * @todo    ERROR LOGGING
             * Log the error once PSR-3 LoggerInterface is implemented.
             */
            throw new StreamRuntimeException(
                "Failed to rewind the stream. " . $exception->getMessage()
            );
        }
    }

    /**
     * `read`
     * 
     * Read the specified number of bytes from the current stream.
     * 
     * @param       int                 $length
     * 
     * The maximum number of bytes to read from the stream.
     * 
     * @return      string              $content
     * 
     * The content contained in the bytes read from the stream.
     * 
     * @throws      StreamRuntimeException
     * 
     * If the stream is closed, invalid, is not seekable, is not
     * readable, or if an error occurs during the read operation.
     */
    public function read(int $length): string
    {
        if ($this->closed || !$this->stream) {
            throw new StreamRuntimeException(
                "Failed to read from the current stream. The stream is closed or invalid."
            );
        }

        if (!$this->seekable || !$this->readable) {
            throw new StreamRuntimeException(
                "Failed to read from the current stream. The stream is not seekable or is not readable."
            );
        }

        if (($content = fread($this->stream, $length)) === false) {
            throw new StreamRuntimeException(
                "Failed to read from the current stream. An error occurred during the read operation."
            );
        }

        return $content;
    }

    /**
     * `write`
     * 
     * Write the specified content to the current stream.
     * 
     * @param       string              $content
     * 
     * The content to write to the stream.
     * 
     * @return      int                 $writtenBytes
     * 
     * The number of bytes written to the stream.
     * 
     * @throws      StreamRuntimeException
     * 
     * If the stream is closed, invalid, is not seekable, is not
     * readable, or if an error occurs during the write operation.
     */
    public function write(string $content): int
    {
        if ($this->closed || !$this->stream) {
            throw new StreamRuntimeException(
                "Failed to read from the current stream. The stream is closed or invalid."
            );
        }

        if (!$this->seekable || !$this->writable) {
            throw new StreamRuntimeException(
                "Failed to write to the current stream. The stream is not seekable or is not writable."
            );
        }

        if (($writtenBytes = fwrite($this->stream, $content, 8192)) === false) {
            throw new StreamRuntimeException(
                "Failed to write to the current stream. An error occurred during the write operation."
            );
        }

        return $writtenBytes;
    }

    /**
     * `detach`
     * 
     * Separates underlying resources from the stream.
     * 
     * @return      resource|null           $resource
     * 
     * The underlying PHP resource, if any.
     */
    public function detach(): mixed
    {
        $resource = null;

        if (is_resource($this->stream)) {
            $resource = $this->stream;
        }

        $this->stream = null;
        $this->metadata = [];
        $this->mode = 'r';
        $this->seekable = false;
        $this->readable = false;
        $this->writable = false;
        $this->size = null;
        $this->closed = true;

        return $resource;
    }

    /**
     * `close`
     * 
     * Closes the stream and any underlying resources.
     * 
     * @return      void
     */
    public function close(): void
    {
        $this->detach();
    }
}
