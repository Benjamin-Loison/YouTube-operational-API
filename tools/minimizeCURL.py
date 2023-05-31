#!/usr/bin/python3

## /!\ Assume that the content of `curl.txt` is trusted /!\

'''
For the moment this algorithm only removes unnecessary headers.
'''

import shlex, subprocess, json

wantedOutput = '1714'

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
    command = f"curl https://URL -H 'Authorization: Bearer {accessToken}'"
    result = executeCommand(command)
    return wantedOutput in result'''

print(len(command))

# Should try to minimize the number of requests done.
while True:
    changedSomething = False
    arguments = shlex.split(command)
    for argumentsIndex in range(len(arguments) - 1):
        argument, nextArgument = arguments[argumentsIndex : argumentsIndex + 2]
        if argument == '-H':
            previousCommand = command
            #print(arguments[argumentsIndex : argumentsIndex + 2])
            del arguments[argumentsIndex : argumentsIndex + 2]
            command = shlex.join(arguments)
            #print(len(command))
            if isCommandStillFine(command):
                print(len(command), 'still fine')
                changedSomething = True
                break
            else:
                command = previousCommand
                arguments = shlex.split(command)
    if not changedSomething:
        break

print(len(command))

with open('minimizedCurl.txt', 'w') as f:
    f.write(command)
