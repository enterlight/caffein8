<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Beverage;
use Illuminate\Support\Facades\Validator;


if ( !function_exists('trace') ) {

    function trace($msg, $die = false) {

        if ( env('APP_DEBUG', false) == false ) {
            return;
        }

        $s = print_r($msg, true);
        $s .= "\n";
        error_log($s, 3, storage_path("logs/trace.log"));
        if ( $die ) {
            echo ('<pre>');
            print_r($s);
            echo ('</pre>');
        }
    }
}

class CaffeineCalculatorController extends Controller
{

    private $combos = [];
    protected function allowedConsumptionValidator(Array $data)
    {

        return Validator::make($data, [
            'consumption' =>  'required|integer|min:0'
        ]);
    }


    /**
     *
     * Returns favorite beverage.
     * Here we could have gotten the authenticated user and then get his favorite beverages.
     * @param Request $request
     * @return array
     */
    public function favorites(Request $request) {

        $beverages = Beverage::all();
        return ['success' => 1, 'data' => $beverages];
    }


    /**
     * Returns a list of different combination of beverages based on the actual consumption
     * @param Request $request
     * @return array
     *      id => qty
     */
    public function options(Request $request)
    {

        trace($request->input('consumption'));

        $validator = $this->allowedConsumptionValidator($request->all());

        if ( $validator->fails() ) {
            return Response::json(array(
                'msg' => 'Invalid Input!'
            ), 400);
        }

        $allowed = env('MAX_DAILY_CAFFEINE_ALLOWANCE', 500) - $request->input('consumption');

        $beverages = Beverage::where('caffeine_mg', '<' , $allowed)
        ->where('caffeine_mg', '>', 0)
        ->whereNotNull('caffeine_mg')
        ->orderBy('caffeine_mg', 'DESC')
        ->get()
        ->toArray();

        if ( count($beverages) <= 0 ) {
            return ['success' => 0, 'msg' => 'You had enough for today'];
        }

        /* @TODO: No need to run the algo for 1 item
         **/
        if ( count($beverages) == 1 ) {
        }

        $savedCombos = $this->findAllCombinations($allowed, $beverages);
        if ( count($savedCombos) > 0 ) {
            return ['success' => 1, 'data' => $savedCombos];
        }

        else {
            return ['success' => 0, 'msg' => 'You had enough for today'];
        }
    }


    /**
     * This is based on the coin problem algorithm.  This version is using recursion.
     * @param $toMakeUpTo : Caffeinge value
     * @param $beverages : list of all beverages that qualify for $toMakeUpTo amount
     * @return array
     */
    private function findAllCombinations($toMakeUpTo, $beverages)
    {
        $idMap = array_column($beverages, 'id');
        $savedCombos = [];
        $amounts = array_column($beverages, 'caffeine_mg');
        foreach($idMap as $i => $id ) {
            $totalMap[$id] = 0;
        }

        $this->findCombo($toMakeUpTo, 0, $amounts, $idMap, $totalMap, $savedCombos);

        trace($savedCombos);

        return $savedCombos;
    }


    /**
     * @param $left                     : Amount left to be processed
     * @param $currentBeverage          : Index of the beverage being processed
     * @param Array $amountsOfCaffeine  :  list of amounts of caffeine
     * @param $idMap                    : Map index => id beverage
     * @param $totalMap                 : Map id beverage => total caffeine
     * @param $savedCombos              : Accumulator for all the combos found
     */
    private function findCombo($left, $currentBeverage, $amountsOfCaffeine, $idMap, &$totalMap, &$savedCombos) {

        // here for the N-1 beverages
        if ($currentBeverage < count($amountsOfCaffeine) - 1) {

            if ( $left > 0 ) {

                $caffeine = $amountsOfCaffeine[$currentBeverage];

                if ( $caffeine <= $left ) {
                    // try all possible numbers of current coin given the amount
                    // that is $left

                    for ($i = 0; $i <= $left / $caffeine; $i++) {
                        $totalMap[$idMap[$currentBeverage]] = $i;
                        $this->findCombo($left - $caffeine * $i, $currentBeverage + 1, $amountsOfCaffeine, $idMap, $totalMap, $savedCombos);
                    }

                    // reset the current coin amount to zero before recursing
                    $totalMap[$idMap[$currentBeverage]] = 0;
                }

                // case when there is a coin whose value is greater than the goal
                else {

                    $this->findCombo($left, $currentBeverage + 1, $amountsOfCaffeine, $idMap, $totalMap, $savedCombos);
                }
            }

            // we've reached our goal, print out the current coin amounts
            else {
                $savedCombos[] = $totalMap;
            }
        }

        // Here for the last beverages
        else {

            // if we have not reached our goal value yet
            if ( $left > 0 ) {

                $caffeine = $amountsOfCaffeine[$currentBeverage];

                if ($caffeine <= $left) {

                    // if the remainder of our goal is evenly divisble by our last
                    // amount value, we can make the goal amount -> we just want the maximum lower or equal to
//                    if ( $left % $caffeine == 0 ) {

                        // add last amount and save the combination
                        $totalMap[$idMap[$currentBeverage]] = intval($left / $caffeine);
                        $savedCombos[] = $totalMap;

                        // reset the total
                        $totalMap[$idMap[$currentBeverage]] = 0;
//                    }

                }
            }
            // we've reached our goal, print out the current coin amounts
            else {
                $savedCombos[] = $totalMap;
            }
        }
    }

}
