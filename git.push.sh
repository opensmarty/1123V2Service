#!/bin/bash
#提交说明参数，命令没带参数，则设m为空

if [ -z $1 ]
then
    m="ok, this is empty info for committing."
else
    m=$1
fi

#根目录
root=$(pwd)
cd $root

#项目名称
project=${root##*/}

#远程分支
branch[0]="git@development:repos/$project.git"
branch[1]="git@github.com:opensmarty/$project.git"
branch[2]="git@git.oschina.net:opensmarty/$project.git"

#提交函数
push(){
    index=0
    for val in ${branch[@]}
    do
        domain=`expr ${val%:*} | cut -d"@" -f2`
        if [[ $domain == "development" ]]
        then
            domain="origin"
        fi
        if [[ $(git remote -v) =~ $val ]]
        then
            echo "分支[$index]:$domain"
            git pull $domain master
            git add .
            git status
            git commit -m $m
            git push $domain master
        else
            git remote add $domain $val
        fi
        ((index++))
        echo ""
    done
}

#执行过程
echo "项目【$project】开始更新"
push
echo "更新结束"
