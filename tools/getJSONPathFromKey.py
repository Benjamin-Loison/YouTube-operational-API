#!/usr/bin/python3

import sys, json

def treatKey(obj, path, key):
    objKey = obj[key]
    objKeyType = type(objKey)
    value = objKey if (not objKeyType is dict and not objKeyType is list) else ''
    # used to be a print
    return (path + '/' + key, value)

def _finditem(obj, key, path = ''):
    objType = type(obj)
    results = []
    if objType is dict:
        keys = obj.keys()
        if key == '':
            for keyTmp in keys:
                results += [treatKey(obj, path, keyTmp)]
        elif key in keys:
            results += [treatKey(obj, path, key)]
        for keyTmp in keys:
            res = _finditem(obj[keyTmp], key, path + '/' + keyTmp)
            if res != []:
                results += res
    elif objType is list:
        objLen = len(obj)
        for objIndex in range(objLen):
            objEl = obj[objIndex]
            res = _finditem(objEl, key, path + '/' + str(objIndex))
            if res != []:
                results += res
    return results
    

filePath = sys.argv[1]
key = sys.argv[2] if len(sys.argv) >= 3 else ''
# `ytVariableName` could be for instance 'ytInitialPlayerResponse'
ytVariableName = sys.argv[3] if len(sys.argv) >= 4 else 'ytInitialData'

# if not found from key could search by value
# that way could find easily shortest path to get the value as sometimes the value is repeated multiple times

with open(filePath) as f:
    isJSON = f.read(1) == '{'

if not isJSON:
    with open(filePath) as f:
        content = f.read()
    newContent = '{' + content.split(ytVariableName + ' = {')[1].split('};')[0] + '}'
    with open(filePath, 'w') as f:
        f.write(newContent)

with open(filePath) as f:
    data = json.load(f)

with open(filePath, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii = False, indent = 4)

pathValues = _finditem(data, key)
for path, value in pathValues:
    print(path, value)
    path = path[1:]
    path = path.replace('/', "']['")
    pathParts = path.split("][")
    pathPartsLen = len(pathParts)
    for pathPartsIndex in range(pathPartsLen):
        pathPart = pathParts[pathPartsIndex][1:-1]
        if pathPart.isdigit():
            pathParts[pathPartsIndex] = pathPart
    path = "][".join(pathParts)
    newPath = "['" + path + "']"
    print(newPath, value)

