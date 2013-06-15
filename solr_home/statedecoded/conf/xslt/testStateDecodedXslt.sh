#!/bin/env bash

DOCS_UNDER_TEST=(10.1-2211.xml 31-10.xml)

for fileToTest in ${DOCS_UNDER_TEST[@]}
do
    echo "Testing $fileToTest"
    echo "Applying "
    expectedResult="$fileToTest.expectedresult";
    if [ ! -e "$fileToTest" ]
    then
        echo "File >$fileToTest< does not exist";
        exit
    elif [ ! -e "$expectedResult" ] 
    then
        echo "File >$expectedResult< does not exist; Cannot test XSLT transform of $fileToTest";
        exit
    fi
    lawDiffs=`diff $expectedResult <(python debugXslt.py $fileToTest stateDecodedXml.xsl)`
    if [ "$lawDiffs" == "" ] 
    then 
        echo "Test Passed!" 
    else
        echo "Test Failed, Unexpected Difference Encountered:"
        echo "-----------------------------------------------"
        echo "$lawDiffs"
    fi
done
