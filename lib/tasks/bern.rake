# encoding: UTF-8

namespace :bern do
  desc "creates bern json file from normalized csv"
  task :generate_json_from_csv do
    budget = OpenBudget::Budget.new
    budget.load_csv 'source/bern/2014/PG-Budget_Stadt_Bern_2014_normalized.csv'
    budget.save_pretty_json 'data/bern/data.json'
  end

  desc "normalize bern csv to generic budget csv"
  task :normalize_csv do
    csv = CSV.read('source/bern/2014/PG-Budget_Stadt_Bern_2014.csv', :encoding => 'iso-8859-1:utf-8', :col_sep => ';', :headers => true)

    levels = [
      'Direktion',
      'Abteilung',
      'Produktegruppe',
      'Produkt'
    ]

    number_headers = []
    csv.headers.each do |header|
      number_header = header.to_s.match(/(?<type>Budget|Rechnung) (?<collection>Aufwand|Ertrag) (?<year>[0-9]{4})/)
      if number_header
        number_headers << {
          key: header,
          collection: number_header[:collection] == 'Aufwand' ? 'expense' : 'revenue',
          type: number_header[:type] == 'Budget' ? 'budgets' : 'accounts',
          year: number_header[:year]
        }
      end
    end

    CSV.open('source/bern/2014/PG-Budget_Stadt_Bern_2014_normalized.csv', 'wb') do |normalize_csv|
      headers = []
      levels.each_index do |index|
        headers += ["level_#{index}_id", "level_#{index}_name"]
      end
      headers += number_headers.collect do |number_header|
        "value_#{number_header[:collection]}_#{number_header[:type]}_#{number_header[:year]}"
      end
      normalize_csv << headers

      row_context_levels = []
      csv.each do |row|
        row_level_ids = levels.collect do |level|
          row[level]
        end.reject(&:blank?)
        row_level = {
          id: row_level_ids.last,
          name: row['Bezeichnung']
        }

        # known duplicate rows
        if %w(P380220 P690120 P850170).include?(row_level[:id]) && row_level[:name].blank?
          next
        end

        if row_level_ids.length == levels.length
          # number row
          if row_context_levels.length != levels.length - 1
            puts "context error:"
            puts "levels #{levels}"
            puts "row_level_ids #{row_level_ids}"
            puts "row_context_levels #{row_context_levels}"
            puts "--"
          else
            values = (row_context_levels + [row_level]).collect do |level|
              [level[:id], level[:name]]
            end
            values += number_headers.collect do |number_header|
              row[number_header[:key]].sub(/-$/, '')
            end
            normalize_csv << values.flatten
          end
        else
          # aggregate row
          # give context
          row_context_level_ids = row_context_levels.collect do |row_context_level|
            row_context_level[:id]
          end

          length = row_level_ids.length - 1
          if row_context_level_ids[0, length] == row_level_ids[0, length]
            row_context_levels = row_context_levels[0, length] + [row_level]
          else
            puts "context error:"
            puts "row_level_ids #{row_level_ids}"
            puts "row_context_levels #{row_context_levels}"
            puts "--"
          end
        end
      end
    end
  end
end
