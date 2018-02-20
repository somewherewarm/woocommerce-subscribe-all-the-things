#!/usr/bin/env bash
# usage: travis.sh before|after

if [ $1 == 'before' ]; then

	if [[ ${RUN_CODE_COVERAGE} != 1 ]]; then
		phpenv config-rm xdebug.ini
	fi
fi

if [ $1 == 'after' ]; then

	if [[ ${RUN_CODE_COVERAGE} == 1 ]]; then
		bash <(curl -s https://codecov.io/bash)
	fi
fi
