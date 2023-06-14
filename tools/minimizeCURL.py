#!/usr/bin/python3

## /!\ Assume that the content of `curl.txt` is trusted /!\
# TODO:  precising or/and lowering this trust level would be interesting

'''
For the moment this algorithm only removes unnecessary:
- headers
- URL parameters
- cookies
- raw data
'''

import shlex, subprocess, json, copy, sys
from urllib.parse import urlparse, parse_qs, quote_plus

# Could precise the input file and possibly remove the output one as the minimized requests start to be short.
if len(sys.argv) < 2:
    print('Usage: ./minimizeCURL "Wanted output"')
    exit(1)

wantedOutput = sys.argv[1]

# The purpose of these parameters is to reduce requests done when developing this script:
removeHeaders = True
removeUrlParameters = True
removeCookies = True
removeRawData = True

# Pay attention to provide a command giving plaintext output, so might required to remove `Accept-Encoding` HTTPS header.
with open('curl.txt') as f:
    command = f.read()

def executeCommand(command):
    # `stderr = subprocess.DEVNULL` is used to get rid of curl progress.
    result = subprocess.check_output(f'{command}', shell = True, stderr = subprocess.DEVNULL).decode('utf-8')
    return result

def isCommandStillFine(command):
    result = executeCommand(command)
    return wantedOutput in result
    '''data = json.loads(result)
    accessToken = data['access_token']
    command = f"curl URL -H 'Authorization: Bearer {accessToken}'"
    result = executeCommand(command)
    return wantedOutput in result'''

print(len(command))

if removeHeaders:
    print('Removing headers')

    # Should try to minimize the number of requests done, by testing half of parameters at each request.
    while True:
        changedSomething = False
        arguments = shlex.split(command)
        for argumentsIndex in range(len(arguments) - 1):
            argument, nextArgument = arguments[argumentsIndex : argumentsIndex + 2]
            if argument == '-H':
                previousCommand = command
                del arguments[argumentsIndex : argumentsIndex + 2]
                command = shlex.join(arguments)
                if isCommandStillFine(command):
                    print(len(command), 'still fine')
                    changedSomething = True
                    break
                else:
                    command = previousCommand
                    arguments = shlex.split(command)
        if not changedSomething:
            break

if removeUrlParameters:
    print('Removing URL parameters')

    arguments = shlex.split(command)
    for argumentsIndex, argument in enumerate(arguments):
        if argument.startswith('http'):
            urlIndex = argumentsIndex
            break

    url = arguments[urlIndex]
    while True:
        changedSomething = False
        urlParsed = urlparse(url)
        query = parse_qs(urlParsed.query)
        for key in list(query):
            previousQuery = copy.deepcopy(query)
            del query[key]
            url = urlParsed._replace(query = '&'.join([f'{parameter}={quote_plus(query[parameter][0])}' for parameter in query])).geturl()
            arguments[urlIndex] = url
            command = shlex.join(arguments)
            if isCommandStillFine(command):
                print(len(command), 'still fine')
                changedSomething = True
                break
            else:
                query = previousQuery
                url = urlParsed._replace(query = '&'.join([f'{parameter}={quote_plus(query[parameter][0])}' for parameter in query])).geturl()
                arguments[urlIndex] = url
                command = shlex.join(arguments)
        if not changedSomething:
            break

if removeCookies:
    print('Removing cookies')

    COOKIES_PREFIX = 'Cookie: '

    arguments = shlex.split(command)
    for argumentsIndex, argument in enumerate(arguments):
        if argument.startswith(COOKIES_PREFIX):
            cookiesIndex = argumentsIndex
            break

    cookies = arguments[cookiesIndex]
    while True:
        changedSomething = False
        cookiesParsed = cookies.replace(COOKIES_PREFIX, '').split('; ')
        for cookiesParsedIndex, cookie in enumerate(cookiesParsed):
            cookiesParsedCopy = cookiesParsed[:]
            del cookiesParsedCopy[cookiesParsedIndex]
            arguments[cookiesIndex] = COOKIES_PREFIX + '; '.join(cookiesParsedCopy)
            command = shlex.join(arguments)
            if isCommandStillFine(command):
                print(len(command), 'still fine')
                changedSomething = True
                cookies = '; '.join(cookiesParsedCopy)
                break
            else:
                arguments[cookiesIndex] = COOKIES_PREFIX + '; '.join(cookiesParsed)
                command = shlex.join(arguments)
        if not changedSomething:
            break

if removeRawData:
    print('Removing raw data')

    arguments = shlex.split(command)
    for argumentsIndex, argument in enumerate(arguments):
        try:
            json.loads(argument)
            rawDataIndex = argumentsIndex
            break
        except:
            pass

    def getPaths(d):
        if isinstance(d, dict):
            for key, value in d.items():
                yield f'/{key}'
                yield from (f'/{key}{p}' for p in getPaths(value))

        elif isinstance(d, list):
            for i, value in enumerate(d):
                yield f'[{i}]'
                yield from (f'[{i}]{p}' for p in getPaths(value))

    rawData = arguments[rawDataIndex]
    while True:
        changedSomething = False
        rawDataParsed = json.loads(rawData)
        # Note that the path goes from parents to children which is quite a wanted behavior to quickly remove useless chunks.
        paths = getPaths(rawDataParsed)
        for pathsIndex, path in enumerate(paths):
            rawDataParsedCopy = copy.deepcopy(rawDataParsed)
            entry = rawDataParsedCopy
            pathParts = path[1:].split('/')
            for pathPart in pathParts[:-1]:
                entry = entry[pathPart]
            del entry[pathParts[-1]]
            arguments[rawDataIndex] = json.dumps(rawDataParsedCopy)
            command = shlex.join(arguments)
            if isCommandStillFine(command):
                print(len(command), 'still fine')
                changedSomething = True
                rawData = json.dumps(rawDataParsedCopy)
                break
            else:
                arguments[rawDataIndex] = json.dumps(rawDataParsed)
                command = shlex.join(arguments)
        if not changedSomething:
            break

command = command.replace(' --compressed', '')

with open('minimizedCurl.txt', 'w') as f:
    f.write(command)
