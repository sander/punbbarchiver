import tarfile
import os

tarname = 'examplearchive.tar.bz2'
tmpdir = '/tmp/punbbarchiver-work'

print 'Content-Type: text/plain; charset=ASCII\n\n'

if os.path.exists(tarname):
  os.remove(tarname)
tar = tarfile.open(tarname, 'w:bz2')
tar.add(tmpdir)
tar.close()

print 'Archive successfully converted to a tar file.'
