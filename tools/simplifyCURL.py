#!/usr/bin/python3

# in fact this tool can be used not only for YouTube but for all automatization based on HTTP

import os, subprocess, re, json

isWSL = True

def getPath(path):
    return path if not isWSL else path.replace('\\', '/').replace('C:', '/mnt/c')

path = getPath('C:\\Users\\Benjamin\\Desktop\\BensFolder\\DEV\\StackOverflow\\YouTube\\')

os.chdir(path)

def exec(cmd):
    return subprocess.check_output(cmd, shell=True).decode('utf-8')

with open('curlCommand.txt') as f:
    line = f.readline()

needle = 'isHearted'

#print(line)

"""

two approaches:

1. block manually some useless pattern
2. automatically remove some patterns while keeping needle retrieved

"""

# beautify: echo '{JSON}' | python -m json.tool

headersToRemove = ['Accept-Encoding', 'User-Agent', 'Accept', 'Accept-Language', 'X-Goog-Visitor-Id', 'Sec-Fetch-Dest', 'DNT', 'Connection', 'Origin', 'X-Youtube-Client-Version', 'X-Youtube-Client-Name', 'Cookie', 'Sec-Fetch-Mode', 'Sec-Fetch-Site', 'Pragma', 'Cache-Control', 'TE'] # likewise more general # 'Referer' required for youtube music
# or could make a whitelist instead
toRemoves = [' -X POST']
# could also make one big but doing like currently we give some semantical structure and can then for instance make bruteforce
toReplaces = [['curl', 'curl -s'], ['2.20220119.05.00', '2.2022011'], ['1.20220125.01.00', '1.2022012'], ['%3D', '=']] # 2.20220201.05.00 -> 2.20220201
#dataRawNeedle = " --data-raw '"
contextToRemoves = ['adSignalsInfo', 'user', 'request', 'clickTracking', 'clientScreenNonce']
clientToRemoves = ['hl', 'gl', 'remoteHost', 'deviceMake', 'deviceModel', 'userAgent', 'osName', 'osVersion', 'originalUrl', 'platform', 'clientFormFactor', 'configInfo', 'browserName', 'browserVersion', 'visitorData', 'screenWidthPoints', 'screenHeightPoints', 'screenPixelDensity', 'screenDensityFloat', 'utcOffsetMinutes', 'userInterfaceTheme', 'mainAppWebInfo', 'timeZone', 'playerType', 'tvAppInfo', 'clientScreen']
generalToRemoves = ['webSearchboxStatsUrl', 'playbackContext', 'cpn', 'captionParams', 'playlistId']

def delete(variable, sub):
    if sub in variable:
        del(variable[sub])

def treat(line):
    for headerToRemove in headersToRemove:
        #line = re.sub(r"-H '" + headerToRemove + ": [^']'", '', line)
        line = re.sub(" -H\s+'" + headerToRemove + "(.+?)'", '', line) # can starts with r"XYZ"
    for toRemove in toRemoves:
        line = line.replace(toRemove, '')
    for toReplace in toReplaces:
        needle, replaceWith = toReplace
        line = line.replace(needle, replaceWith)
    #if dataRawNeedle in line:
    regex = "--data-raw\s+'(.+?)'"
    search = re.search(regex, line)

    if search:
        dataRaw = search.group(1)
        #print(dataRaw)
        #lineParts = line.split(dataRawNeedle)
        #linePartsParts = lineParts[1].split("'")
        #dataRaw = linePartsParts[0] # could also use a regex
        dataRawJson = json.loads(dataRaw)
        for contextToRemove in contextToRemoves:
            delete(dataRawJson['context'], contextToRemove)
        for clientToRemove in clientToRemoves:
            delete(dataRawJson['context']['client'], clientToRemove) # could generalize with n arguments with ... notation
        #del(dataRawJson['webSearchboxStatsUrl'])
        for generalToRemove in generalToRemoves:
            delete(dataRawJson, generalToRemove)
        newDataRaw = json.dumps(dataRawJson, separators=(',', ':'))
        #print(json.dumps(dataRawJson, separators=(',', ':'), indent = 4))
        line = re.sub(regex, "--data-raw '" + newDataRaw + "'", line)
        #line = lineParts[0] + dataRawNeedle + newDataRaw + "'"# + linePartsParts[1]
    return line

cmd = treat(line)
print(cmd)
res = exec(cmd)
if needle in res:
    print('working')
else:
    print('not working:')
    print(res)
#print(res)

