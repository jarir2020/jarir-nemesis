# Queues & Background Jobs Documentation

Queues allow you to defer the processing of a time-consuming task, such as sending an email, until a later time, significantly speeding up web requests.

---

## Configuration

Set your `QUEUE_DRIVER` in the `.env` file:
- `sync`: Processes jobs immediately (useful for development).
- `database`: Stores jobs in a database table.
- `redis`: Uses Redis for job storage.

---

## Creating Jobs

You can generate a new job class using the CLI:

```bash
php nemesis make:job SendEmailJob
```

### Job Class Structure

```php
namespace App\Jobs;

use Nemesis\Queue\Job;

class SendEmailJob extends Job {
    protected $email;

    public function __construct($email) {
        $this->email = $email;
    }

    public function handle() {
        // Logic to send email
    }
}
```

---

## Dispatching Jobs

To push a job onto the queue, use the `Queue` class:

```php
use Nemesis\Queue\Queue;
use App\Jobs\SendEmailJob;

Queue::push(new SendEmailJob('user@example.com'));
```

---

## Running the Queue Worker

To process queued jobs, run the `queue:work` command:

```bash
php nemesis queue:work
```

This command will remain running and process jobs as they enter the queue.
