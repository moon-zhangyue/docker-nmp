<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\service\UserService;
use app\service\KafkaService;
use think\facade\Log;

class ConsumeRegistration extends Command
{
    protected function configure()
    {
        $this->setName('consume:registration')
             ->setDescription('Consume user registration messages from Kafka');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('Starting user registration consumer...');
        
        try {
            $kafkaService = new KafkaService();
            $userService = new UserService();
            
            $output->writeln('Connected to Kafka broker: ' . env('KAFKA_BROKERS', 'localhost:9092'));
            $output->writeln('Using consumer group: ' . env('KAFKA_GROUP_ID', 'user-registration-group'));
            
            $kafkaService->consumeUserRegistrationMessages(function($userData) use ($userService, $output) {
                try {
                    $output->writeln('Processing registration for user: ' . $userData['username']);
                    $userService->processRegistration($userData);
                    $output->writeln('Registration processed successfully');
                } catch (\Exception $e) {
                    $output->writeln('Error processing registration: ' . $e->getMessage());
                    Log::error('Consumer error: {message}', ['message' => $e->getMessage()]);
                }
            });
        } catch (\Exception $e) {
            $output->writeln('Consumer error: ' . $e->getMessage());
            Log::error('Consumer error: {message}', ['message' => $e->getMessage()]);
            return 1;
        }
    }
}