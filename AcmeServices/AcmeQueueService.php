<?php
/**
 * Interacting with AWS SQS service
 */
namespace Acme\ApiBundle\Services;

use Aws\Sqs\SqsClient;

class AcmeQueueService
{

    protected $aws_region;
    protected $aws_key;
    protected $aws_secret;
    protected $aws_bucket;
    protected $sqs;
    protected $aws_sqs_notifications_queue;

    public function __construct($aws_region, $aws_key, $aws_secret, $aws_bucket, $aws_sqs_notifications_queue)
    {
        $this->aws_region = $aws_region;
        $this->aws_key = $aws_key;
        $this->aws_secret = $aws_secret;
        $this->aws_bucket = $aws_bucket;
        $this->aws_sqs_notifications_queue = $aws_sqs_notifications_queue;

        $this->sqs = new SqsClient([
            'version' => 'latest',
            'region' => $this->aws_region,
            'credentials' => array(
                'key' => $this->aws_key,
                'secret' => $this->aws_secret
            )
        ]);

    }

    /**
     * Send message to queue
     * @param $message
     */
    public function send($message)
    {

        $this->sqs->sendMessage(array(
            'QueueUrl'    => $this->aws_sqs_notifications_queue,
            'MessageBody' => $message,
        ));
    }

    /**
     * Fetch message from queue
     * @return array|mixed
     */
    public function fetch()
    {
        $result = $this->sqs->receiveMessage(array(
            'QueueUrl' => $this->aws_sqs_notifications_queue,
        ));

        $receivedMessage = array();
        $messages = $result->get('Messages');

        if (is_array($messages))
        {
            foreach ($messages as $messageBody) {
                if (!empty($messageBody))
                {
                    // contains Body (as json), and ReceiptHandle
                    $receivedMessage = $messageBody;
                }
            }
        }

        return $receivedMessage;
    }

    public function delete($receiptHandle)
    {
        $this->sqs->deleteMessage(array(
            'QueueUrl' => $this->aws_sqs_notifications_queue,
            'ReceiptHandle' => $receiptHandle
        ));
    }

}