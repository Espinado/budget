[Version]
Class=IEXPRESS
SEDVersion=3
[Options]
PackagePurpose=InstallApp
ShowInstallProgramWindow=1
HideExtractAnimation=1
UseLongFileName=1
InsideCompressed=0
CAB_FixedSize=0
CAB_ResvCodeSigning=0
RebootMode=N
InstallPrompt=%InstallPrompt%
DisplayLicense=
FinishMessage=%FinishMessage%
TargetName=%TargetName%
FriendlyName=%FriendlyName%
AppLaunched=%AppLaunched%
PostInstallCmd=<None>
AdminQuietInstCmd=
UserQuietInstCmd=
SourceFiles=SourceFiles
[Strings]
InstallPrompt=Install Family Money on this computer?
FinishMessage=Family Money is installed. Open it from the desktop shortcut.
TargetName=C:\laragon\www\budget\public\downloads\FamilyMoney-Setup.exe
FriendlyName=Family Money
AppLaunched=cmd /c install.cmd
FILE0="install.cmd"
FILE1="FamilyMoney.cmd"
FILE2="favicon.ico"
[SourceFiles]
SourceFiles0=C:\laragon\www\budget\installer\
[SourceFiles0]
%FILE0%=
%FILE1%=
%FILE2%=
