<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * "فرم ارزیابی عملکرد و جدول امتیازبندی پرسنل — نسخه ۶، ۱۴۰۴".
 *
 * منبع: کاربندی این سرویس عیناً از روی کاربرگ اکسل واقعی («فرم ارزیابی-نسخه6.1404»
 * و «جدول امتیاز بندی و رتبه بندی») استخراج شده است؛ نسخه قدیمی‌تر («فرم ارزیابی-
 * نسخه 1») که در همان فایل بود عمداً نادیده گرفته شده چون کاربر خواسته نسخه جدید
 * ملاک باشد.
 *
 * الگوریتم امتیازدهی:
 *   - فرم شامل ۱۶ شاخص در ۵ دسته («انضباط حرفه‌ای»، «شایستگی»، «آموزش و فراگیری»،
 *     «بازاریابی و برندسازی و تولید محتوا»، «عملکرد پرسنل و مدیریت مشتریان») است.
 *   - هر شاخص یک «ضریب» (weight) دارد و امتیاز خام هر شاخص یکی از چهار مقدار است:
 *       عالی=۳، خوب=۲، متوسط=۱، ضعیف=۰  (ستون‌های عالی/خوب/متوسط/ضعیف در کاربرگ)
 *   - امتیاز موزون هر شاخص = score × weight  (سقف هر شاخص = weight × 3، دقیقاً برابر
 *     همان مقدار ستون «رتبه» در کاربرگ اصلی، مثلاً شاخصی با ضریب ۳ سقفش ۹ است).
 *   - مجموع سقف همه ۱۶ شاخص = ۹۶ و به‌علاوه حداکثر ۴ «امتیاز ویژه از دیدگاه مدیریت»
 *     یعنی سقف کل «نتیجه عملکرد» دقیقاً ۱۰۰ می‌شود — همان‌طور که در کاربرگ اصلی آمده.
 *   - نتیجه عملکرد = Σ(weighted_points) + special_score  (نهایتاً بین ۰ تا ۱۰۰).
 *
 * آستانه‌های رتبه‌بندی (از «جدول امتیاز بندی و رتبه بندی»، عیناً):
 *   ۸۰ تا ۱۰۰  => بسیار اثربخش / رتبه «ممتاز»   => EXCELLENT
 *   ۷۰ تا ۷۹   => اثربخش / رتبه «عالی»          => GREAT
 *   ۶۰ تا ۶۹   => مطلوب / رتبه «خوب»            => GOOD
 *   ۵۰ تا ۵۹   => نیاز به عارضه‌یابی / رتبه «متوسط» => AVERAGE
 *   کمتر از ۵۰ => نامطلوب / رتبه «ضعیف»          => WEAK
 */
final class PerformanceEvaluationService
{
    /** حداکثر امتیاز ویژه قابل‌ثبت توسط مدیریت. */
    public const MAX_SPECIAL_SCORE = 4.0;

    /**
     * ۱۶ شاخص رسمی فرم ارزیابی به همراه ضریب هرکدام، به ترتیب دقیق کاربرگ اصلی.
     * @return array<int, array{category:string, indicator:string, weight:int}>
     */
    public static function rubric(): array
    {
        return [
            ['category' => 'انضباط حرفه‌ای', 'weight' => 2, 'indicator' =>
                'پوشش آراسته و مناسب فعالیت‌های زیبایی - وضعیت مناسب میکاپ/ناخن/مو/مژه در سالن - روحیه شاد و مثبت (ضریب ۲)'],
            ['category' => 'انضباط حرفه‌ای', 'weight' => 1, 'indicator' =>
                'رعایت نظافت عمومی و شخصی در محل کار (محیط کار / ابزار کار)'],
            ['category' => 'شایستگی', 'weight' => 1, 'indicator' =>
                'توانایی تولید ایده ارزشمند و نوآوری در کار (خلاقیت و نوآوری)'],
            ['category' => 'شایستگی', 'weight' => 1, 'indicator' =>
                'رضایت مشتری (مشاوره حرفه‌ای به مشتری - کیفیت بالای خدمات - کیفیت بالای برخورد با مشتری)'],
            ['category' => 'شایستگی', 'weight' => 1, 'indicator' =>
                'نحوه برخورد، معاشرت، همکاری و تعامل با کلیه همکاران / مدیر داخلی سالن'],
            ['category' => 'آموزش و فراگیری', 'weight' => 3, 'indicator' =>
                'فعالیت اثربخش در لاین آموزش (ارایه تقویم آموزشی - معرفی هنرجو - تولید محتوای آموزشی) (ضریب ۳)'],
            ['category' => 'آموزش و فراگیری', 'weight' => 3, 'indicator' =>
                'حضور در دوره‌های آموزشی جهت ارتقای مهارت‌های تخصصی (خودآموزی) (ضریب ۳)'],
            ['category' => 'بازاریابی و برندسازی و تولید محتوا', 'weight' => 2, 'indicator' =>
                'مشارکت در کمپین‌های باشگاه مشتریان سالن (ضریب ۲)'],
            ['category' => 'بازاریابی و برندسازی و تولید محتوا', 'weight' => 2, 'indicator' =>
                'اقدام به بازاریابی شخصی (هر نوع تبلیغات مؤثر و به‌روز) (ضریب ۲)'],
            ['category' => 'بازاریابی و برندسازی و تولید محتوا', 'weight' => 2, 'indicator' =>
                'تولید محتوا و اشتراک‌گذاری محتوا با سالن (خلاقانه / نوآورانه / اثربخش) (ضریب ۲)'],
            ['category' => 'بازاریابی و برندسازی و تولید محتوا', 'weight' => 2, 'indicator' =>
                'فعالیت اثربخش در شبکه‌های اجتماعی با هدف جذب مشتری جدید و ارایه گزارش بازاریابی ماهیانه (ضریب ۲)'],
            ['category' => 'عملکرد پرسنل و مدیریت مشتریان', 'weight' => 3, 'indicator' =>
                'تعداد مشتریان جدید (عملکرد در جذب و افزایش تعداد مشتریان جدید از طرف پرسنل) (ضریب ۳)'],
            ['category' => 'عملکرد پرسنل و مدیریت مشتریان', 'weight' => 2, 'indicator' =>
                'وفادارسازی مشتریان (افزایش بازگشت مشتریان / کاهش مشتریان از دست‌رفته) (ضریب ۲)'],
            ['category' => 'عملکرد پرسنل و مدیریت مشتریان', 'weight' => 2, 'indicator' =>
                'عملکرد مبلغ فروش خدمات (پیشرفت در فروش) (ضریب ۲)'],
            ['category' => 'عملکرد پرسنل و مدیریت مشتریان', 'weight' => 3, 'indicator' =>
                'عملکرد توزیع مشتریان در سالن (گزارشات بازاریابی پورسانت / گزارشات پرسنل) (ضریب ۳)'],
            ['category' => 'عملکرد پرسنل و مدیریت مشتریان', 'weight' => 2, 'indicator' =>
                'رتبه و جایگاه (سهم فروش خدمات از لاین) (ضریب ۲)'],
        ];
    }

    /**
     * جدول امتیاز→رتبه و متن دقیق پاداش/جریمه، عیناً از «جدول امتیاز بندی و رتبه بندی».
     * @return array<int, array{min:float, max:float, level:string, rank:string, result:string, actions:string, policy:string}>
     */
    public static function levelTable(): array
    {
        return [
            [
                'min' => 80, 'max' => 100, 'level' => 'EXCELLENT', 'rank' => 'ممتاز', 'result' => 'بسیار اثربخش',
                'actions' => 'شامل پاداش ویژه',
                'policy'  => 'در صورت احراز رتبه ممتاز معافیت مالیات برای ۳ ماه. در صورت تکرار متوالی رتبه ممتاز، افزایش ۵ درصد حق مشارکت همان ماه. '
                           . 'در صورت تکرار متوالی رتبه ممتاز برای بار دوم، افزایش ۱۰ درصد حق مشارکت همان ماه. در صورت تکرار بار سوم، قرارداد جدید با شرایط ویژه.',
            ],
            [
                'min' => 70, 'max' => 79.999, 'level' => 'GREAT', 'rank' => 'عالی', 'result' => 'اثربخش',
                'actions' => 'شامل پاداش تشویقی',
                'policy'  => 'در صورت احراز رتبه عالی، پاداش معافیت مالیات همان ماه. در صورت تکرار متوالی رتبه عالی، معافیت مالیات برای ۲ ماه. '
                           . 'در صورت ارتقا به رتبه ممتاز، ۵ درصد افزایش درصد مشارکت برای ۱ ماه.',
            ],
            [
                'min' => 60, 'max' => 69.999, 'level' => 'GOOD', 'rank' => 'خوب', 'result' => 'مطلوب',
                'actions' => 'شامل عارضه‌یابی جهت رشد بیشتر - پاداش تشویقی',
                'policy'  => 'در صورت احراز رتبه خوب، اقدام به ارزیابی و تمرکز بر نقاط قوت و ضعف و ارایه برنامه رشد جهت ارتقای رتبه. '
                           . 'در صورت تکرار رتبه در مرتبه دوم متوالی، کاهش ۳ درصد مالیات پرداختی همان ماه. در صورت ارتقا به رتبه عالی، کاهش ۵ درصد مالیات پرداختی برای ۱ ماه.',
            ],
            [
                'min' => 50, 'max' => 59.999, 'level' => 'AVERAGE', 'rank' => 'متوسط', 'result' => 'نیاز به عارضه‌یابی',
                'actions' => 'شامل تذکر کتبی و عارضه‌یابی در مرتبه اول - جریمه در مرتبه دوم - قطع همکاری در مرتبه سوم',
                'policy'  => 'در صورت احراز رتبه متوسط در مرتبه اول، تذکر و اقدام به عارضه‌یابی. در صورت احراز رتبه متوسط در مرتبه متوالی دوم، جریمه ۵ درصد حق مشارکت. '
                           . 'در صورت احراز رتبه متوسط در مرتبه متوالی سوم، جریمه ۱۰ درصد حق مشارکت و قطع همکاری.',
            ],
            [
                'min' => -1000, 'max' => 49.999, 'level' => 'WEAK', 'rank' => 'ضعیف', 'result' => 'نامطلوب',
                'actions' => 'شامل اخطار کتبی و جریمه و عارضه‌یابی در مرتبه اول - قطع همکاری در مرتبه دوم',
                'policy'  => 'در صورت احراز رتبه ضعیف در مرتبه اول، جریمه ۵ درصد کاهش درصد حق مشارکت. '
                           . 'در صورت احراز رتبه ضعیف در دو مرتبه متوالی، جریمه ۱۵ درصد حق مشارکت و قطع همکاری.',
            ],
        ];
    }

    /** ردیف جدول رتبه‌بندی متناظر با یک امتیاز نهایی مشخص را برمی‌گرداند. */
    public static function levelFor(float $score): array
    {
        foreach (self::levelTable() as $row) {
            if ($score >= $row['min'] && $score <= $row['max']) {
                return $row;
            }
        }
        return self::levelTable()[array_key_last(self::levelTable())];
    }

    /**
     * از روی امتیازهای خام ثبت‌شده (0..3) برای هر شاخص رابریک + امتیاز ویژه مدیریت،
     * امتیاز موزون هر ردیف و نتیجه نهایی را محاسبه می‌کند.
     *
     * @param array<int,int|null> $scores شاخص‌شماره(۰-پایه) => امتیاز خام ۰..۳
     * @return array{items:array, total:float, special:float, level:string, rank:string, result:string, actions:string, policy:string}
     */
    public static function calculate(array $scores, float $specialScore): array
    {
        $specialScore = max(0.0, min(self::MAX_SPECIAL_SCORE, $specialScore));
        $items = [];
        $total = 0.0;
        foreach (self::rubric() as $i => $row) {
            $raw = isset($scores[$i]) ? max(0, min(3, (int)$scores[$i])) : null;
            $weighted = $raw !== null ? (float)$raw * $row['weight'] : 0.0;
            $total += $weighted;
            $items[] = $row + ['sort_order' => $i, 'score' => $raw, 'weighted_points' => round($weighted, 2), 'max_points' => $row['weight'] * 3];
        }
        $total = round($total + $specialScore, 2);
        $total = max(0.0, min(100.0, $total));
        $band  = self::levelFor($total);

        return [
            'items'   => $items,
            'total'   => $total,
            'special' => $specialScore,
            'level'   => $band['level'],
            'rank'    => $band['rank'],
            'result'  => $band['result'],
            'actions' => $band['actions'],
            'policy'  => $band['policy'],
        ];
    }

    /** ارزیابی کامل (سرسند + ردیف‌ها) را در تراکنش ذخیره می‌کند و شناسه را برمی‌گرداند. */
    public static function save(int $staffId, string $periodStart, string $periodEnd, array $scores, float $specialScore, ?int $evaluatedBy, ?string $summary, string $status = 'FINAL'): int
    {
        $calc = self::calculate($scores, $specialScore);
        $db   = Database::instance();

        return $db->transaction(function () use ($db, $staffId, $periodStart, $periodEnd, $calc, $evaluatedBy, $summary, $status) {
            $id = $db->insert('performance_evaluations', [
                'staff_id'      => $staffId,
                'period_start'  => $periodStart,
                'period_end'    => $periodEnd,
                'total_score'   => $calc['total'],
                'special_score' => $calc['special'],
                'level'         => $calc['level'],
                'rank_label'    => $calc['rank'],
                'summary'       => $summary,
                'evaluated_by'  => $evaluatedBy,
                'status'        => $status,
            ]);
            foreach ($calc['items'] as $item) {
                $db->insert('performance_evaluation_items', [
                    'evaluation_id'   => $id,
                    'category'        => $item['category'],
                    'indicator'       => $item['indicator'],
                    'weight'          => $item['weight'],
                    'score'           => $item['score'],
                    'weighted_points' => $item['weighted_points'],
                    'sort_order'      => $item['sort_order'],
                ]);
            }
            return $id;
        });
    }
}
