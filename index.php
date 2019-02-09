<?php

/**
 * Created by PhpStorm.
 * User: Maxim Ermakov
 * Date: 08.02.2019
 * Time: 14:43
 */

ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once( './phpmorphy/src/common.php');


class IntermediateWords {

    public function __construct($repeat, $randomOffset, $dictOptimize) {

        // Словари
        $dir = './phpmorphy/dicts';
        $lang = 'ru_RU';

        //Опции
        $opts = array(
            'storage' => PHPMORPHY_STORAGE_FILE, //Из файла
        );
        $this->morphy = new phpMorphy($dir, $lang, $opts);
        $this->repeat = $repeat;
        $this->randomOffset = $randomOffset;
        $this->dictOptimize = $dictOptimize;
    }

    private $morphy = null;
    protected $randomOffset = false;
    protected $dictOptimize = false;
    public $repeat = 0;
    protected $dictVowels = ['А', 'О', 'И', 'Е', 'Э', 'Ы', 'У', 'Ю', 'Я'];
    protected $dictСonsonants = ['Б', 'В', 'Г', 'Д', 'Ж', 'З', 'К', 'Л', 'М', 'Н', 'П', 'Р', 'С', 'Т', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ы', 'Ь'];
    protected $dictAll = ['А', 'О', 'И', 'Е', 'Э', 'Ы', 'У', 'Ю', 'Я', 'Б', 'В', 'Г', 'Д', 'Ж', 'З', 'К', 'Л', 'М', 'Н', 'П', 'Р', 'С', 'Т', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ы', 'Ь'];
    //Цепочки
    protected $aChildFind = [
        0 => [
            'word' => '',
            'parent' => -1
        ]
    ];
    //Массив всех найденных слов
    private $aChildFindList = [];
    //Найденые слова с индексом
    private $aResult = [];

    //Определить букву как гласную
    public function checkVowels($char) {

        return in_array($char, $this->dictVowels);
    }

    public function getChild($iStartWord, $endWord) {

        $startWord = $this->aChildFind[$iStartWord]['word'];

        if ($iStartWord > $this->repeat) {
            return;
        }
        $len = mb_strlen($startWord);
        //По одной букве слева направо
        for ($i = 0; $i < $len; $i++) {
            if ($this->randomOffset) {
                $char = mb_substr($startWord, rand(0, $len-1), 1);
            } else {
                $char = mb_substr($startWord, $i, 1);
            }
            /**
             * Если используется оптимизация алфафита, то программа пытается определить гласные буквы
             * и не подставлять вместо них согласные, но это ограничивает вариативность. Зато быстрее
             */
            if ($this->dictOptimize) {
                if ($this->checkVowels($char)) {
                    $dictChar = $this->dictVowels;
                } else {
                    $dictChar = $this->dictСonsonants;
                }
            } else {
                $dictChar = $this->dictAll;
            }

            //Заменим букву из словаря и проверим на наличие
            foreach ($dictChar as $dictCharItem) {
                /**
                 * todo Тут надо бы собирать слово чтобы не заменялись две похожие буквы, но для простоты сделал через str_replace
                 */
                $childWord = str_replace($char, $dictCharItem, $startWord);

                if (!in_array($childWord, $this->aChildFindList)) {

                    $this->aChildFindList[] = $childWord;
                    try {
                        if(false === ($paradigms = $this->morphy->findWord($childWord, $this->morphy::IGNORE_PREDICT))) {
                            continue;
                        }
                        if ($childWord == $endWord OR $childWord != $startWord AND !in_array($childWord, $this->aChildFind)) {
                            $this->aChildFind[] = [
                                'word' => $childWord,
                                'parent' => $iStartWord
                            ];

                            if ($childWord == $endWord) {
                                $this->aResult[] = count($this->aChildFind) - 1;
                            }
                        }
                        if ($childWord == $endWord) {
                            return;
                        }

                    } catch(phpMorphy_Exception $e) {
                        die('Error occured while creating phpMorphy instance: ' . $e->getMessage());
                    } catch (\Exception $e) {
                        echo 'У вас точно PHP 7?';
                    }
                }
            }
        }
        $iChild = $iStartWord + 1;
        $this->getChild($iChild, $endWord);
    }

    /**
     *
     * @param $startWord
     * @param $endWord
     */
    public function getIntermediateWords($startWord, $endWord) {

        $this->aChildFind[0]['word'] = $startWord;
        $this->aChildFindList[] = $startWord;
        $this->getChild(0, $endWord);
    }

    /**
     * Скачет по дереву вверх доставая все промежуточные слова
     * @param $iResultWord
     * @param array $haystack
     * @return array
     */
    private function getResult($iResultWord, $haystack = []) {

        if (!is_array($haystack))
            $haystack = [];

        if ($iResultWord == -1) {
            return array_reverse($haystack);
        }
        $haystack[] = $this->aChildFind[$iResultWord]['word'];
        return $this->getResult($this->aChildFind[$iResultWord]['parent'], $haystack);

    }

    /**
     * Доастает всё найденные цепочки
     * @return array
     */
    public function showResult() {

        $res = [];
        foreach ($this->aResult as $value) {
            $res[] = $this->getResult($value);
        }
        return $res;
    }
}


$startWord = trim($_POST['start']);
$endWord = trim($_POST['end']);
$randOffset = (bool)isset($_POST['randOffset']);
$repeat = (int)($_POST['repeat']);
$dictOptimize = (bool)isset($_POST['dictOptimize']);

$startWord = mb_strtoupper($startWord);
$endWord = mb_strtoupper($endWord);

$obj = new IntermediateWords($repeat, $randOffset, false);
$obj->getIntermediateWords($startWord, $endWord);
?>


<html>

    <head>
        <title>Игра метаграммы</title>
    </head>
    <body>
    <p>Можно ли сделать из мухи слона? Запросто, если чему-то незначительному вы придадите слишком большое значение.</p>
    <p>Эта программа создана решать эти проблемы. К сожалению программа работает не идеально, т.к. размер словаря не как у яндекса</p>

    <p>Программа работает так:
        лужа -> ложа -> кожа -> ... -> море
        Т.е., меняя за 1 шаг по 1 букве, словарными словами и распечататывает все шаги.
    </p>

    <form action="/" method="post">
        <input name="start" type="text" id="start" value="<?php if (!empty($startWord)) { ?><?= $startWord ?> <?php } else { ?>ЛУЖА<?php } ?>">
        <label for="start">Начально слово</label>
        <br>
        <input name="end" type="text" id="end" value="<?php if (!empty($endWord)) { ?><?= $endWord ?> <?php } else { ?>МОРЕ<?php } ?>">
        <label for="end">Cлово заканчивающие метаграмму</label>
        <hr>
        <input name="randOffset" type="checkbox" id="randOffset" <?php if ($randOffset) { ?>checked<?php } ?>>
        <label for="randOffset">Использовать случайный перебор</label>
        <input type="text" name="repeat" id="repeat" value="<?php if (!empty($repeat)) { ?><?= $repeat ?> <?php } else { ?>3000<?php } ?>">
        <label for="repeat">Количество попыток для нахождения метаграммы</label>
        <hr>
        <input name="dictOptimize" type="checkbox" id="dictOptimize" <?php if ($dictOptimize) { ?>checked<?php } ?>>
        <label for="dictOptimize">Использовать оптимизацию алфафита. Взамен гласным не подставляются согласные. (Влияет на производительность).</label>
        <hr>
        <input type="submit" value="Решить">

    </form>
    </body>


    <h2>Результат</h2>
    <?php
        foreach ($obj->showResult() as $blockchain) {
            $lastIndex = count($blockchain) - 1;
            ?>
            <div>
            <?php foreach ($blockchain as $key => $word) { ?>
                <?= $word ?> <? if ($key < $lastIndex) { ?> > <?php } ?>
            <?php } ?>
            </div>
        <?php }
    ?>

</html>
