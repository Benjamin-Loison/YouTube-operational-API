#!/usr/bin/python3

## /!\ Assume that the content of `curl.txt` is trusted /!\

'''
For the moment this algorithm only:
- removes unnecessary headers
- removes unnecessary URL parameters
- removes unnecessary cookies
'''

# TODO: add booleans switches

import shlex, subprocess, json, copy, sys
from urllib.parse import urlparse, parse_qs, quote_plus

if len(sys.argv) < 2:
    print('Usage: ./minimizeCURL "Wanted output"')
    exit(1)

wantedOutput = sys.argv[1]

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

command = command.replace(' --compressed', '')

with open('minimizedCurl.txt', 'w') as f:
    f.write(command)
