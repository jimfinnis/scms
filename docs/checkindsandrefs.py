#!/usr/bin/python
#
# This script reads in all the tags which have been
# indexed by indtag or secindtag, and all those in the refs.txt (i.e.
# all those defined in the system) and prints out any in one list but
# not another

import os,re

os.system("grep indtag *.tex >quap")
list1lines=dict()
list2lines=dict()

r = re.compile("\{(.*)\}")
indexedindocs = list()
for l in open("quap"):
    m = r.search(l)
    if m:
        s = m.group(1)
        if "!" in s:
            (dummy,s) = s.split("!")
        indexedindocs.append(s)
        list1lines[s]=l

os.unlink("quap")

inreference=list()
for l in open("tmp.txt"):
    if len(l)>10 and "(" in l:
        lst = l.split(" ")
        s = lst[0][2:-1]
        inreference.append(s)
        list2lines[s]=l
        

print "In reference list but not documented:"
for x in [x for x in inreference if not x in indexedindocs]:
    print x        
    print list2lines[x]
print "\n\nDocumented but not in reference list:"
for x in [x for x in indexedindocs if not x in inreference]:
    print x        
    print list1lines[x]
        
