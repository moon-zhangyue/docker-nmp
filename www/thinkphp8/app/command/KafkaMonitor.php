<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\TopicPartition;

class KafkaMonitor extends Command
{
    protected function configure()
    {
        $this->setName('kafka:monitor')
             ->setDescription('Monitor Kafka messages')
             ->addArgument('action', Argument::REQUIRED, 'Action to perform (list/consume)')
             ->addOption('topic', null, Option::VALUE_OPTIONAL, 'Topic name', 'user-registration')
             ->addOption('partition', null, Option::VALUE_OPTIONAL, 'Partition number', 0)
             ->addOption('offset', null, Option::VALUE_OPTIONAL, 'Starting offset', RD_KAFKA_OFFSET_BEGINNING)
             ->addOption('limit', null, Option::VALUE_OPTIONAL, 'Number of messages to show', 10);
    }

    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        $topic = $input->getOption('topic');
        $partition = (int)$input->getOption('partition');
        $offset = $input->getOption('offset');
        $limit = (int)$input->getOption('limit');

        switch ($action) {
            case 'list':
                $this->listTopics($output);
                break;
            case 'consume':
                $this->consumeMessages($output, $topic, $partition, $offset, $limit);
                break;
            default:
                $output->writeln("<error>Invalid action. Use 'list' or 'consume'</error>");
                return 1;
        }
    }

    protected function listTopics(Output $output)
    {
        try {
            $conf = new Conf();
            $conf->set('metadata.broker.list', env('KAFKA_BROKERS', 'localhost:9092'));
            
            $consumer = new \RdKafka\Consumer($conf);
            $metadata = $consumer->getMetadata(true, null, 60000);

            $output->writeln("\n<info>Available Kafka Topics:</info>");
            foreach ($metadata->getTopics() as $topic) {
                $output->writeln(sprintf(
                    "Topic: %s (Partitions: %d)",
                    $topic->getTopic(),
                    count($topic->getPartitions())
                ));
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            return 1;
        }
    }

    protected function consumeMessages(Output $output, string $topic, int $partition, $offset, int $limit)
    {
        try {
            $conf = new Conf();
            $conf->set('metadata.broker.list', env('KAFKA_BROKERS', 'localhost:9092'));
            $conf->set('group.id', 'monitor-' . uniqid());
            $conf->set('auto.offset.reset', 'earliest');

            $consumer = new KafkaConsumer($conf);
            
            // 设置分区和偏移量
            $topicPartition = new TopicPartition($topic, $partition);
            $topicPartition->setOffset($offset);
            $consumer->assign([$topicPartition]);

            $output->writeln(sprintf(
                "\n<info>Reading messages from topic '%s' (partition: %d, limit: %d)</info>\n",
                $topic,
                $partition,
                $limit
            ));

            $count = 0;
            while ($count < $limit) {
                $message = $consumer->consume(1000);
                
                switch ($message->err) {
                    case RD_KAFKA_RESP_ERR_NO_ERROR:
                        $count++;
                        $this->displayMessage($output, $message);
                        break;
                        
                    case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                        $output->writeln("<comment>No more messages available.</comment>");
                        return;
                        
                    case RD_KAFKA_RESP_ERR__TIMED_OUT:
                        $output->writeln("<comment>Timed out waiting for messages.</comment>");
                        return;
                        
                    default:
                        $output->writeln("<error>Error: {$message->errstr()}</error>");
                        return;
                }
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            return 1;
        }
    }

    protected function displayMessage(Output $output, $message)
    {
        try {
            $data = json_decode($message->payload, true);
            $timestamp = $message->timestamp;
            
            $output->writeln(sprintf(
                "Offset: %d\nTimestamp: %s\nKey: %s\nData: %s\n%s\n",
                $message->offset,
                date('Y-m-d H:i:s', $timestamp/1000),
                $message->key ?? 'null',
                json_encode($data, JSON_PRETTY_PRINT),
                str_repeat('-', 50)
            ));
        } catch (\Exception $e) {
            $output->writeln("<error>Error displaying message: " . $e->getMessage() . "</error>");
        }
    }
} 