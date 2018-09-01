<?php namespace Model\Highcharts;

use Model\Core\Module;

class Highcharts extends Module
{
	/**
	 * @param $list
	 * @param array $options
	 * @throws \Exception
	 */
	public function lineChart($list, array $options = [])
	{
		$options = array_merge([
			'id' => 'line-chart',
			'fields' => [],
			'label' => null,
			'label-type' => null, // supported at the moment: datetime
			'values-type' => null, // supported at the moment: price
		], $options);

		$chartOptions = [
			'chart' => [
				'zoomType' => 'x',
				'scrollablePlotArea' => [
					'minWidth' => 700,
					'scrollPositionX' => 1.
				],
			],
			'title' => [
				'text' => '',
			],
			'yAxis' => [
				'title' => [
					'text' => $this->getLabel($options['fields'][0] ?? ''),
				],
			],
			'xAxis' => [
				'title' => [
					'text' => $this->getLabel($options['label'] ?: ''),
				],
			],
			'series' => [],
		];

		switch ($options['label-type']) {
			case 'datetime':
				$chartOptions['xAxis']['type'] = 'datetime';
				break;
		}

		foreach ($options['fields'] as $idx => $f) {
			if (is_numeric($idx))
				$label = $this->getLabel($f);
			else
				$label = $idx;
			$chartOptions['series'][$idx] = [
				'name' => $label,
				'data' => [],
			];
		}

		foreach ($list as $elIdx => $el) {
			foreach ($options['fields'] as $idx => $f) {
				$label = $options['label'] ? $el[$options['label']] : $elIdx;
				$v = $el[$f];
				switch ($options['label-type']) {
					case 'datetime':
						$label = 'virgdelDate.parse(\'' . $label . '\')virgdel';
						break;
				}

				$chartOptions['series'][$idx]['data'][] = [
					$label,
					$v,
				];
			}
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
			chartOptions['tooltip'] = {'pointFormat': '{series.name}: <b>{point.y:,.2f}€</b>'};
			<?php
			break;
			}
			?>
			Highcharts.chart('<?= entities($options['id']) ?>', chartOptions);
		</script>
		<?php
	}

	/**
	 * @param $list
	 * @param array $options
	 * @throws \Exception
	 */
	public function pieChart($list, array $options = [])
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