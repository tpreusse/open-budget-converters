module OpenBudget
  module Mappers
    module Generic
      def load_csv file_path
        csv = CSV.read(file_path, :headers => true)

        levels = []
        value_headers = []
        csv.headers.each do |header|
          level_id_header = header.to_s.match(/level_(?<index>[0-9]+)_id/)
          if level_id_header
            levels << level_id_header[:index]
          end
          value_header = header.to_s.match(/value_(?<collection>.+)_(?<type>.+)_(?<year>[0-9]{4})/)
          if value_header
            value_headers << {
              key: header,
              collection: value_header[:collection],
              type: value_header[:type],
              year: value_header[:year]
            }
          end
        end

        levels.sort!

        csv.each do |row|
          ids = []
          names = []
          levels.each do |level|
            ids << row["level_#{level}_id"]
            names << row["level_#{level}_name"]
          end
          # ToDo: check for blanks & duplicate nodes
          node = get_or_create_node ids, names

          value_headers.each do |header|
            node.add(
              header[:collection],
              header[:type],
              header[:year],
              row[header[:key]].to_f
            )
          end
        end
      end
    end
  end
end
