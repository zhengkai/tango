#!/bin/bash

cd $(dirname `readlink -f $0`)

./test_a_1.php &
./test_a_1.php &
./test_b_3.php &
./test_b_3.php &
./test_b_3.php &
./test_b_3.php &

wait
