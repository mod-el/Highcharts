<?php namespace Model\Highcharts;

use Model\Core\Module;

class Highcharts extends Module
{
	/**
	 * @param iterable $list
	 * @param array $options
	 * @throws \Exception
	 */
	public function lineChart(iterable $list, array $options = [])
	{
		$options = array_merge([
			'type' => 'line',
			'id' => 'line-chart',
			'fields' => [],
			'label' => null,
			'label-type' => null, // supported at the moment: datetime
			'values-type' => null, // supported at the moment: price
			'plot-lines' => [],
			'annotations' => [],
			'tooltip-split' => false,
		], $options);

		$chartOptions = [
			'chart' => [
				'zoomType' => 'x',
				'scrollablePlotArea' => [
					'minWidth' => 700,
					'scrollPositionX' => 1.
				],
				'events' => [],
			],
			'plotOptions' => [
				'series' => [
					'dataLabels' => [
						'shape' => 'callout',
						'backgroundColor' => 'rgba(0, 0, 0, 0.67)',
						'style' => [
							'color' => '#FFFFFF',
							'textShadow' => 'none',
						],
					],
				],
			],
			'title' => [
				'text' => '',
			],
			'tooltip' => [
				'split' => $options['tooltip-split'],
			],
			'yAxis' => [
				'title' => [
					'text' => '',
				],
				'plotLines' => is_callable($options['plot-lines']) ? $options['plot-lines']() : $options['plot-lines'],
			],
			'xAxis' => [
				'title' => [
					'text' => $this->getLabel($options['label'] ?: ''),
				],
			],
			'series' => [],
		];

		if ($options['type'] === 'area') {
			$chartOptions['chart']['type'] = 'area';
			$chartOptions['plotOptions']['area']['stacking'] = 'normal';
			$chartOptions['plotOptions']['area']['marker']['enabled'] = false;
		}

		switch ($options['label-type']) {
			case 'datetime':
				$chartOptions['xAxis']['type'] = 'datetime';
				break;
		}

		// Check if fields is an associative array or a numeric one
		$is_fields_assoc = false;
		$c_idx = 0;
		foreach ($options['fields'] as $idx => $f) {
			if ($c_idx !== $idx) {
				$is_fields_assoc = true;
				break;
			}
			$c_idx++;
		}

		$cf = 0;
		foreach ($options['fields'] as $idx => $f) {
			if (!$is_fields_assoc)
				$label = $this->getLabel($f);
			else
				$label = $f;
			$chartOptions['series'][$cf++] = [
				'name' => $label,
				'data' => [],
			];
		}

		foreach ($list as $elIdx => $el) {
			$cf = 0;
			foreach ($options['fields'] as $idx => $f) {
				$label = $options['label'] ? $el[$options['label']] : $elIdx;
				$v = $el[$is_fields_assoc ? $idx : $f];
				switch ($options['label-type']) {
					case 'datetime':
						$label = 'virgdelDate.parse(\'' . $label . '\')virgdel';
						break;
				}

				$chartOptions['series'][$cf++]['data'][] = [
					$label,
					$v,
				];
			}

			// Annotations handling. I look for the right point to add the annotation to
			switch ($options['label-type']) {
				case 'datetime':
					$label = $el[$options['label']] ? date_create($el[$options['label']]) : '';
					break;
			}

			foreach ($options['annotations'] as &$annotation) {
				if (isset($annotation['point-set']))
					continue;
				if ($annotation['point'] <= $label) {
					$annotation['point'] = $elIdx;
					$annotation['point-set'] = true;
				}
			}
			unset($annotation);
		}

		if ($options['annotations']) {
			?>
			<script>
				function highchartsAddAnnotations() {
					<?php
					foreach ($options['annotations'] as $annotation) {
					if (!isset($annotation['point-set']))
						continue;
					?>
					this.series[<?=($annotation['series'] ?? 0)?>].points[<?=$annotation['point']?>].update({
						dataLabels: <?=json_encode(array_merge(['enabled' => true], $annotation['annotation']))?>
					});
					<?php
					}
					?>
				}
			</script>
			<?php
		}
		?>
		<div id="<?= entities($options['id']) ?>"></div>
		<script>
			var chartOptions = <?=str_replace(['"virgdel', 'virgdel"'], '', json_encode($chartOptions))?>;
			<?php
			switch ($options['values-type']) {
			case 'price':
			?>
			chartOptions['yAxis']['labels'] = {
				'formatter': function () {
					return makePrice(this.value).replace('&euro;', '€');
				}
			};
			chartOptions['tooltip']['pointFormat'] = '{series.name}: <b>{point.y:,.2f}€</b>';
			<?php
			break;
			}

			if ($options['annotations']){
			?>
			chartOptions['chart']['events']['load'] = highchartsAddAnnotations;
			<?php
			}
			?>
			Highcharts.chart('<?= entities($options['id']) ?>', chartOptions);
		</script>
		<?php
	}

	/**
	 * @param iterable $list
	 * @param array $options
	 * @throws \Exception
	 */
	public function stackedBarChart(iterable $list, array $options = [])
	{
		$options = array_merge([
			'id' => 'bar-chart',
			'labels' => [],
			'height' => null,
		], $options);

		if (is_callable($options['height']))
			$options['height'] = $options['height']($this->model);

		if (is_callable($options['labels']))
			$options['labels'] = $options['labels']($this->model);

		$chartOptions = [
			'chart' => [
				'type' => 'bar',
				'height' => $options['height'],
			],
			'title' => [
				'text' => '',
			],
			'xAxis' => [
				'categories' => $options['labels'],
			],
			'yAxis' => [
				'title' => [
					'text' => '',
				],
			],
			'legend' => [
				'reversed' => true,
			],
			'plotOptions' => [
				'series' => [
					'stacking' => 'normal',
				],
			],
			'series' => $list,
		];

		/*switch ($options['label-type']) {
			case 'datetime':
				$chartOptions['xAxis']['type'] = 'datetime';
				break;
		}*/
		?>
		<div id="<?= entities($options['id']) ?>"></div>
		<script>
			var chartOptions = <?=str_replace(['"virgdel', 'virgdel"'], '', json_encode($chartOptions))?>;
			<?php
			/*switch ($options['values-type']) {
			case 'price':
			?>
			chartOptions['yAxis']['labels'] = {
				'formatter': function () {
					return makePrice(this.value).replace('&euro;', '€');
				}
			};
			chartOptions['tooltip']['pointFormat'] = '{series.name}: <b>{point.y:,.2f}€</b>';
			<?php
			break;
			}*/
			?>
			Highcharts.chart('<?= entities($options['id']) ?>', chartOptions);
		</script>
		<?php
	}

	/**
	 * @param iterable $list
	 * @param array $options
	 * @throws \Exception
	 */
	public function areaChart(iterable $list, array $options = [])
	{
		$options['type'] = 'area';
		return $this->lineChart($list, $options);
	}

	/**
	 * @param iterable $list
	 * @param array $options
	 * @throws \Exception
	 */
	public function pieChart(iterable $list, array $options = [])
	{
		$options = array_merge([
			'id' => 'pie-chart',
			'field' => null,
			'label' => null,
			'text' => null,
			'label-type' => null, // supported at the moment: datetime
			'values-type' => null, // supported at the moment: price
			'onclick' => null,
			'drilldown' => null,
		], $options);

		$chartOptions = [
			'chart' => [
				'type' => 'pie',
			],
			'title' => [
				'text' => '',
			],
			'plotOptions' => [
				'pie' => [
					'allowPointSelect' => true,
					'cursor' => 'pointer',
					'showInLegend' => false,
				],
			],
			'series' => [],
			'responsive' => [
				'rules' => [
					[
						'condition' => [
							'maxWidth' => 480,
						],
						'chartOptions' => [
							'plotOptions' => [
								'pie' => [
									'dataLabels' => [
										'enabled' => false,
									],
									'showInLegend' => true,
								],
							],
						],
					],
				],
			],
		];

		// For donut charts
		$numbersDirection = null;
		foreach ($list as $el) {
			$pointId = $el[$options['label']] ?? '';

			if ($options['text']) {
				if (!is_string($options['text']) and is_callable($options['text']))
					$label = call_user_func($options['text'], $el);
				else
					$label = $options['text'];
			} else {
				if (is_object($el)) {
					$form = $el->getForm();
					if ($form[$options['label']])
						$label = $form[$options['label']]->getText();
					else
						$label = $pointId;
				} else {
					$label = $pointId;
				}
			}

			$value = $el[$options['field']];
			if (!is_numeric($value)) {
				echo 'Unsupported non-numeric value for pie chart';
				return;
			}

			if ($numbersDirection) {
				if (($numbersDirection == 1 and $value < 0) or ($numbersDirection == -1 and $value > 0)) {
					echo 'Cannot mix negative and positive numbers in a pie chart';
					return;
				}
			} else {
				$numbersDirection = $value > 0 ? 1 : -1;
			}
			if ($value < 0)
				$value = abs($value);

			$drilldown = [];
			if ($options['drilldown']) {
				if (!is_string($options['drilldown']) and is_callable($options['drilldown'])) {
					$drilldown = $options['drilldown']($el);
				} else {
					$drilldown = [
						[
							'id' => $el[$options['drilldown']],
							'label' => '',
						],
					];
				}
			}

			if (empty($drilldown)) {
				$drilldown[] = [
					'id' => $pointId,
					'label' => $label,
				];
			}

			$sortingIdx = [];
			foreach ($drilldown as $layerIdx => $layer) {
				$sortingIdx[] = str_pad($layer['id'], 20, '0', STR_PAD_LEFT);

				if (!isset($chartOptions['series'][$layerIdx])) {
					$chartOptions['series'][$layerIdx] = [
						'name' => $this->getLabel($options['field']),
						'data' => [],
					];
				}

				$sortingIdxStr = implode('-', $sortingIdx);

				if (!isset($chartOptions['series'][$layerIdx]['data'][$sortingIdxStr])) {
					$chartOptions['series'][$layerIdx]['data'][$sortingIdxStr] = [
						'id' => $layer['id'],
						'name' => $layer['label'],
						'y' => 0,
					];
				}

				$chartOptions['series'][$layerIdx]['data'][$sortingIdxStr]['y'] += $value;
			}
		}

		// Creo i livelli superiori per dati esistenti solo a quelli inferiori
		$previousData = [];
		foreach ($chartOptions['series'] as &$series) {
			foreach ($previousData as $idx => $datum) {
				$found = false;
				foreach ($series['data'] as $dataIdx => $currentDatum) {
					if (strpos($dataIdx, $idx) === 0) {
						$found = true;
						break;
					}
				}

				if (!$found)
					$series['data'][$idx . '-' . str_pad($datum['id'], 20, '0', STR_PAD_LEFT)] = $datum;
			}

			$previousData = $series['data'];
		}
		unset($series);

		// Riordino tutti i livelli e calcolo dimensioni e altre opzioni
		$previousSize = 0;
		foreach ($chartOptions['series'] as $idx => &$series) {
			ksort($series['data']);
			$series['data'] = array_values($series['data']);

			$layerIdx = $idx + 1;
			$totLayers = count($chartOptions['series']);

			$size = $previousSize + $this->getPieLayerSize($layerIdx, $totLayers);
			$series['size'] = $size . '%';
			if ($previousSize)
				$series['innerSize'] = round($previousSize * 1.02) . '%';
			$previousSize = $size;

			if ($layerIdx < $totLayers)
				$series['dataLabels'] = false;
		}
		unset($series);
		?>
		<div id="<?= entities($options['id']) ?>"></div>
		<script>
			var chartOptions = <?=json_encode($chartOptions)?>;
			<?php
			switch ($options['values-type']) {
			case 'price':
			?>
			chartOptions['tooltip'] = {'pointFormat': '{series.name}: <b>{point.y:,.2f}€</b>'};
			<?php
			break;
			}

			if ($options['onclick']) {
			?>
			chartOptions['plotOptions']['pie']['events'] = {
				'click': function (event) {
					<?=$options['onclick']?>
				}
			};
			<?php
			}
			?>
			Highcharts.chart('<?= entities($options['id']) ?>', chartOptions);
		</script>
		<?php
	}

	/**
	 * Converts a field name in a human-readable label
	 *
	 * @param string $k
	 * @return string
	 */
	public function getLabel(string $k): string
	{
		return ucwords(str_replace(array('-', '_'), ' ', $k));
	}

	/**
	 * @param int $idx
	 * @param int $tot
	 * @return int
	 */
	public function getPieLayerSize(int $idx, int $tot): int
	{
		$sommatoria = 0;
		for ($i = 1; $i <= $tot; $i++)
			$sommatoria += $i;

		$inverso = $tot - $idx + 1;

		return round($inverso / $sommatoria * 100);
	}
}
