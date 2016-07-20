#!/bin/bash

pushd $(dirname $0) > /dev/null

echo;
echo "> Running Unit Tests";
echo;

../vendor/bin/phpunit --color -c UnitTests.xml $1

export typo3DatabaseName="typo3_test";
export typo3DatabaseHost="127.0.0.1";
export typo3DatabaseUsername="root";
export typo3DatabasePassword="";

echo;
echo "> Running Functional Tests";
echo;

../vendor/bin/phpunit --color -c FunctionalTests.xml $1

popd > /dev/null
