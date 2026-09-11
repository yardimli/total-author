<?php
namespace App\Services;

class BookChatLock
{
    private $handle = null;

    public function acquire(int $bookId): bool
    {
        // OS locks release on process death as well as normal completion. Never unlink
        // these files: replacing the inode could let two workers lock the same book.
        $this->handle = fopen(storage_path('framework/cache/book-chat-'.$bookId.'.lock'), 'c');
        if ($this->handle === false) {
            throw new \RuntimeException(__('Unable to start the chat request. Please try again.'));
        }
        if (! flock($this->handle, LOCK_EX | LOCK_NB)) {
            fclose($this->handle);
            $this->handle = null;
            return false;
        }
        return true;
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
