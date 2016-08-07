#!/bin/bash

if [ $1 = "ADD" ]; then
    echo;
    echo "> Setup Fanout Test";
    echo;
    rabbitmqadmin declare queue name=fanout1 auto_delete=false durable=true arguments='{"x-dead-letter-exchange":"typo3.direct", "x-dead-letter-routing-key":"failed-direct"}';
    rabbitmqadmin declare queue name=fanout2 auto_delete=true durable=true arguments='{"x-dead-letter-exchange":"typo3.direct", "x-dead-letter-routing-key":"failed-direct"}';
    rabbitmqadmin declare exchange name=fanout.test type=fanout auto_delete=false internal=false durable=true;
    rabbitmqadmin declare binding source=fanout.test destination=fanout1 destination_type=queue;
    rabbitmqadmin declare binding source=fanout.test destination=fanout2 destination_type=queue;

    echo;
    echo "> Setup Topic Test";
    echo;
    rabbitmqadmin declare queue name=topic1 auto_delete=false durable=true arguments='{"x-dead-letter-exchange":"typo3.direct", "x-dead-letter-routing-key":"failed-direct"}';
    rabbitmqadmin declare queue name=topic2 auto_delete=false durable=true arguments='{"x-dead-letter-exchange":"typo3.direct", "x-dead-letter-routing-key":"failed-direct"}';
    rabbitmqadmin declare exchange name=topic.test type=topic auto_delete=false internal=false durable=true;
    rabbitmqadmin declare binding source=topic.test destination=topic1 destination_type=queue routing_key='*.topic1';
    rabbitmqadmin declare binding source=topic.test destination=topic2 destination_type=queue routing_key='topictest.*';
fi

if [ $1 = "REMOVE" ]; then
    echo;
    echo "> Removing Test Setup";
    echo;

    rabbitmqadmin delete queue name=fanout1
    rabbitmqadmin delete queue name=fanout2
    rabbitmqadmin delete exchange name=fanout.test

    rabbitmqadmin delete queue name=topic1
    rabbitmqadmin delete queue name=topic2
    rabbitmqadmin delete exchange name=topic.test

    rabbitmqadmin delete queue name=default
    rabbitmqadmin delete queue name=internal
    rabbitmqadmin delete queue name=test
    rabbitmqadmin delete queue name=failed
    rabbitmqadmin delete exchange name=typo3.direct

fi

