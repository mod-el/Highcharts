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
					$label = date_create($el[$options['label']]);
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
			'series' => [
				[
					'name' => $this->getLabel($options['field']),
					'data' => [],
				],
			],
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

		if ($options['drilldown']) {
			$chartOptions['series'] = [
				0 => [
					'name' => $this->getLabel(is_string($options['drilldown']) ? $options['drilldown'] : ''),
					'data' => [],
					'size' => '78%',
					'dataLabels' => false,
				],
				1 => array_merge($chartOptions['series'][0], [
					'size' => '100%',
					'innerSize' => '80%',
				]),
			];
		}

		// For donut charts
		$macro = [];
		$sub = [];

		$numbersDirection = null;
		foreach ($list as $elIdx => $el) {
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

			if ($options['drilldown']) {
				if (!is_string($options['drilldown']) and is_callable($options['drilldown'])) {
					$drilldownField = null;
					$drilldown = call_user_func($options['drilldown'], $el);
					$drilldownOn = $drilldown['id'];
					$drilldownLabel = $drilldown['label'];
				} else {
					$drilldownField = $options['drilldown'];
					$drilldownOn = $el[$drilldownField];
					$drilldownLabel = null;
				}
				if (!isset($macro[$drilldownOn])) {
					if ($drilldownLabel === null and $drilldownField) {
						if (is_object($el)) {
							$form = $el->getForm();
							if ($form[$drilldownField])
								$drilldownLabel = $form[$drilldownField]->getText();
							else
								$drilldownLabel = $el[$drilldownField];
						} else {
							$drilldownLabel = $el[$drilldownField];
						}
					}
					$macro[$drilldownOn] = [
						'id' => $drilldownOn,
						'label' => $drilldownLabel,
						'v' => 0,
					];

					$sub[$drilldownOn] = [];
				}
				$macro[$drilldownOn]['v'] += $value;

				$sub[$drilldownOn][] = [
					'id' => $pointId,
					'name' => $label,
					'y' => $value,
				];
			} else {
				$chartOptions['series'][0]['data'][] = [
					'id' => $pointId,
					'name' => $label,
					'y' => $value,
				];
			}
		}

		if ($options['drilldown']) {
			ksort($macro);
			ksort($sub);

			foreach ($macro as $cat) {
				$chartOptions['series'][0]['data'][] = [
					'id' => $cat['id'],
					'name' => $cat['label'],
					'y' => $cat['v'],
				];
			}

			foreach ($sub as $cat => $data) {
				foreach ($data as $d) {
					$chartOptions['series'][1]['data'][] = $d;
				}
			}
		}
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
}
