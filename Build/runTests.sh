#!/bin/bash

pushd $(dirname $0) > /dev/null

../vendor/bin/phpunit --color -c UnitTests.xml $1

popd > /dev/null
