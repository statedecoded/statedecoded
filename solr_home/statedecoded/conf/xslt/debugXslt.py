from lxml import etree
from lxml.etree import XSLTApplyError


if __name__ == "__main__":
    from sys import argv
    # Apply xslt file at argv[2] to xml file at argv[1]
    result = ""
    try:
        xmlFile = etree.parse(open(argv[1]))
        xslt = etree.parse(open(argv[2]))
        transform = etree.XSLT(xslt)
        result = transform(xmlFile)
    except XSLTApplyError as e:
        print "EXCEPTION THROWN PROCESSING XSLT"
        print str(e)
        print "------------------------------------"
    print transform.error_log
    print "------------------------------------"
    print result
