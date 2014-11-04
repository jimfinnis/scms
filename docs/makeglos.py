#!/usr/bin/python

import sys

def jcfgetlabel(n):
    n = n.replace(' ','')
    n = n.capitalize()
    return 'gls'+n
    
for x in [x.strip() for x in open(sys.argv[1]).readlines()]:
    k = x.split(':',2)
    if len(k)==2:
        label=k[0]
        name=k[0]
        desc=k[1]
        print "\\storeglosentry{%s}{name={%s},description={%s}}" % (label,name,desc)
    if len(k)==3:
        label=k[0]
        name=k[1]
        desc=k[2]
        print "\\storeglosentry{%s}{name={%s},description={%s}}" % (label,name,desc)
        

    
