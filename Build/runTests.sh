#!/bin/bash

pushd $(dirname $0) > /dev/null

FAILED=0;

TESTS=$1

if [ $# -lt 1 ]; then
    TESTS="ALL";
fi

if [ $TESTS = "UNIT" ] || [ $TESTS = "ALL" ]; then

    echo;
    echo "> Running Unit Tests";
    echo;

    ../vendor/bin/phpunit --color -c UnitTests.xml

    if [ $? -ne 0 ]; then
      FAILED=1
    fi

fi

if [ $TESTS = "FUNCTIONAL" ] || [ $TESTS = "ALL" ]; then

    export typo3DatabaseName="typo3_test";
    export typo3DatabaseHost="127.0.0.1";
    export typo3DatabaseUsername="root";
    export typo3DatabasePassword="";

    ./setupRabbitMQ.sh ADD

    echo;
    echo "> Running Functional Tests";
    echo;

    ../vendor/bin/phpunit --color -c FunctionalTests.xml
    if [ $? -ne 0 ]; then
      FAILED=1
    fi

    ./setupRabbitMQ.sh REMOVE

fi


if [ $FAILED -ne 0 ]; then
  exit 1;
fi

popd > /dev/null
